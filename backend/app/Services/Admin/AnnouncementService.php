<?php

namespace App\Services\Admin;

use App\Models\Announcement;
use App\Models\PersonnelRecord;
use App\Models\PushToken;
use App\Models\User;
use App\Services\Push\PushMessage;
use App\Services\Push\PushSenderInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Sends an announcement to every active officer in an audience: one in-app
 * notification each (listed by GET /api/v1/notifications) plus a push to
 * their registered devices, delivered on the queue.
 */
class AnnouncementService
{
    /** audience type => personnel_records column (null = everyone) */
    public const AUDIENCES = [
        'all' => null,
        'directorate' => 'directorate',
        'command' => 'command',
        'zone' => 'zone',
        'rank' => 'rank',
    ];

    private const CHUNK = 500;

    /** @return array<string, Collection<int, string>> */
    public function audienceOptions(): array
    {
        $options = [];
        foreach (self::AUDIENCES as $type => $column) {
            if ($column) {
                $options[$type] = PersonnelRecord::whereNotNull($column)->where($column, '<>', '')
                    ->distinct()->orderBy($column)->pluck($column);
            }
        }

        return $options;
    }

    /** @return Builder<User> */
    public function recipients(string $type, ?string $value): Builder
    {
        $query = User::query()->where('users.account_state', User::STATE_ACTIVE);
        $column = self::AUDIENCES[$type] ?? null;
        if ($column) {
            $query->join('personnel_records', 'personnel_records.id', '=', 'users.personnel_record_id')
                ->where("personnel_records.$column", $value);
        }

        return $query->select('users.id');
    }

    /**
     * @param  array{title: string, body: string, priority: string, audience_type: string, audience_value: ?string}  $data
     */
    public function send(array $data, User $sender): Announcement
    {
        $announcement = DB::transaction(function () use ($data, $sender) {
            $announcement = Announcement::create($data + ['sent_by' => $sender->id, 'recipients_count' => 0]);
            $payload = json_encode([
                'announcement_id' => $announcement->id,
                'priority' => $announcement->priority,
            ]);

            $count = 0;
            $this->recipients($data['audience_type'], $data['audience_value'])
                ->chunkById(self::CHUNK, function ($users) use ($announcement, $payload, &$count) {
                    $now = Carbon::now();
                    DB::table('notifications')->insert($users->map(fn ($u) => [
                        'id' => (string) Str::uuid(),
                        'user_id' => $u->id,
                        'type' => 'announcement',
                        'title' => $announcement->title,
                        'body' => $announcement->body,
                        'data' => $payload,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->all());
                    $count += $users->count();
                }, 'users.id', 'id');

            $announcement->update(['recipients_count' => $count]);

            return $announcement;
        });

        $this->queuePush($announcement);

        return $announcement;
    }

    /** Fans the push out on the queue so the admin isn't kept waiting. */
    private function queuePush(Announcement $announcement): void
    {
        if ($announcement->recipients_count === 0) {
            return;
        }
        $announcementId = $announcement->id;

        dispatch(function () use ($announcementId) {
            app(AnnouncementService::class)->deliverPush($announcementId);
        })->afterCommit();
    }

    /** Sends the push for an announcement to its recipients' device tokens. */
    public function deliverPush(string $announcementId): void
    {
        $announcement = Announcement::find($announcementId);
        if (! $announcement) {
            return;
        }
        $push = app(PushSenderInterface::class);
        $message = new PushMessage($announcement->title, Str::limit($announcement->body, 180), [
            'type' => 'announcement',
            'announcement_id' => $announcement->id,
            'priority' => $announcement->priority,
        ]);

        PushToken::query()
            ->whereIn('user_id', $this->recipients($announcement->audience_type, $announcement->audience_value))
            ->select(['id', 'provider', 'token'])
            ->chunkById(self::CHUNK, function ($tokens) use ($push, $message) {
                foreach ($tokens as $t) {
                    try {
                        $push->send($t->provider, $t->token, $message);
                    } catch (\Throwable $e) {
                        report($e); // one bad token must not stop the rest
                    }
                }
            });
    }
}
