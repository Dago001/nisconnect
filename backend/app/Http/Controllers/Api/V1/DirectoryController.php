<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Support\AuditLogger;
use App\Services\Support\PrivacyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * NIS personnel directory. Search is rate limited (route middleware) and
 * capped in size to prevent bulk personnel enumeration. Only registered,
 * active officers are returned with their approved fields.
 */
class DirectoryController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly PrivacyService $privacy,
    ) {}

    public function search(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'directorate' => ['nullable', 'string', 'max:120'],
            'department' => ['nullable', 'string', 'max:120'],
            'command' => ['nullable', 'string', 'max:120'],
            'rank' => ['nullable', 'string', 'max:120'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:25'],
        ]);

        // Require at least one filter — no "list everyone" scraping.
        $filters = array_filter([
            $data['q'] ?? null, $data['directorate'] ?? null, $data['department'] ?? null,
            $data['command'] ?? null, $data['rank'] ?? null,
        ]);
        if (empty($filters)) {
            return response()->json(['message' => 'Provide a search term.'], 422);
        }

        $query = User::query()
            ->where('users.account_state', User::STATE_ACTIVE)
            ->where('users.id', '!=', $request->user()->id)
            ->join('personnel_records', 'personnel_records.id', '=', 'users.personnel_record_id')
            ->select('users.*');

        if (! empty($data['q'])) {
            $term = '%'.str_replace(['%', '_'], ['\%', '\_'], $data['q']).'%';
            $query->where(function ($q) use ($term) {
                $q->where('personnel_records.surname', 'ilike', $term)
                    ->orWhere('personnel_records.first_name', 'ilike', $term)
                    ->orWhere('personnel_records.other_name', 'ilike', $term)
                    ->orWhere('users.service_number', 'ilike', $term);
            });
        }
        foreach (['directorate', 'department', 'command', 'rank'] as $field) {
            if (! empty($data[$field])) {
                $query->where("personnel_records.$field", 'ilike', '%'.$data[$field].'%');
            }
        }

        $results = $query->with('personnelRecord')
            ->orderBy('personnel_records.surname')
            ->paginate($data['per_page'] ?? 15);

        $this->audit->log('directory.search', actorId: $request->user()->id,
            metadata: ['filters' => array_keys(array_filter($data)), 'count' => $results->total()]);

        return response()->json([
            'data' => collect($results->items())->map(fn (User $u) => $this->officerCard($u, $request->user())),
            'meta' => [
                'current_page' => $results->currentPage(),
                'last_page' => $results->lastPage(),
                'total' => $results->total(),
            ],
        ]);
    }

    public function show(Request $request, string $serviceNumber): JsonResponse
    {
        $user = User::where('service_number', $serviceNumber)
            ->where('account_state', User::STATE_ACTIVE)
            ->with('personnelRecord')
            ->first();

        if (! $user) {
            return response()->json(['message' => 'Officer not found.'], 404);
        }

        return response()->json(['data' => $this->officerCard($user, $request->user())]);
    }

    /**
     * @return array<string, mixed>
     */
    private function officerCard(User $user, User $viewer): array
    {
        $p = $user->personnelRecord;

        // Presence / last-seen respect the target officer's privacy settings.
        $presenceVisible = $this->privacy->isVisible($user, $viewer, 'online');
        $lastSeenVisible = $this->privacy->isVisible($user, $viewer, 'last_seen');

        return [
            'id' => $user->id,
            'service_number' => $user->service_number,
            'display_name' => $user->display_name,
            'avatar_url' => $user->avatar_path,
            'rank' => $p?->rank,
            'directorate' => $p?->directorate,
            'department' => $p?->department,
            'zone' => $p?->zone,
            'command' => $p?->command,
            'formation' => $p?->formation,
            'unit' => $p?->unit,
            'presence' => $presenceVisible ? $user->presence : null,
            'last_seen_at' => $lastSeenVisible ? $user->last_seen_at : null,
        ];
    }
}
