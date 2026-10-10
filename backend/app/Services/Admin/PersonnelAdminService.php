<?php

namespace App\Services\Admin;

use App\Models\PersonnelRecord;
use App\Models\User;
use App\Services\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Personnel records maintained from the admin portal (single edits and CSV
 * imports). Keeps linked accounts consistent: when a record stops being
 * "active", any active account for it is suspended and signed out.
 */
class PersonnelAdminService
{
    public const STATUSES = ['active', 'retired', 'suspended', 'dismissed'];

    /** CSV template / import columns, in order. */
    public const COLUMNS = [
        'service_number', 'surname', 'first_name', 'other_name', 'rank', 'directorate',
        'department', 'zone', 'command', 'formation', 'unit', 'posting', 'official_email', 'status',
    ];

    public function __construct(private readonly AuditLogger $audit) {}

    /** The account (if any) registered for a personnel record. */
    public function accountFor(PersonnelRecord $record): ?User
    {
        return User::where('personnel_record_id', $record->id)
            ->orWhere('service_number', $record->service_number)
            ->first();
    }

    /**
     * Suspends the active account linked to a record whose status is no longer
     * "active". Returns the suspended user, or null when nothing changed.
     */
    public function suspendAccountIfInactive(PersonnelRecord $record, User $actor): ?User
    {
        if ($record->status === 'active') {
            return null;
        }
        $user = $this->accountFor($record);
        if (! $user || $user->account_state !== User::STATE_ACTIVE || $this->isProtected($user, $actor)) {
            return null;
        }

        $user->forceFill(['account_state' => User::STATE_SUSPENDED])->save();
        $user->tokens()->delete();

        $this->audit->log('officer.suspended', actorId: $actor->id, resourceType: 'user', resourceId: $user->id,
            metadata: ['reason' => 'personnel_status_'.$record->status, 'personnel_record_id' => $record->id]);
        $this->audit->security('account.suspended', userId: $user->id, severity: 'warning',
            metadata: ['reason' => 'personnel_status_'.$record->status, 'by' => $actor->id]);

        return $user;
    }

    /**
     * Accounts an administrator must not suspend through personnel changes:
     * their own, and (unless they are one) a super administrator's.
     */
    public function isProtected(User $user, User $actor): bool
    {
        return $user->id === $actor->id || ($user->isSuperAdmin() && ! $actor->isSuperAdmin());
    }

    /**
     * Validation rules for one personnel row.
     *
     * @return array<string, mixed>
     */
    public function rules(?string $ignoreId = null): array
    {
        $text = ['nullable', 'string', 'max:255'];

        return [
            'service_number' => ['required', 'string', 'regex:/^[0-9]{1,20}$/',
                Rule::unique('personnel_records', 'service_number')->ignore($ignoreId)],
            'surname' => ['required', 'string', 'max:255'],
            'first_name' => ['required', 'string', 'max:255'],
            'other_name' => $text,
            'rank' => $text,
            'directorate' => $text,
            'department' => $text,
            'zone' => $text,
            'command' => $text,
            'formation' => $text,
            'unit' => $text,
            'posting' => $text,
            'official_email' => ['nullable', 'email', 'max:255'],
            'status' => ['required', Rule::in(self::STATUSES)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'service_number.required' => 'Enter the Service Number.',
            'service_number.regex' => 'A Service Number may contain digits only (up to 20).',
            'service_number.unique' => 'A personnel record with this Service Number already exists.',
            'surname.required' => 'Enter the surname.',
            'first_name.required' => 'Enter the first name.',
            'official_email.email' => 'Enter a valid official email address.',
            'status.in' => 'Choose a valid status: active, retired, suspended or dismissed.',
            'status.required' => 'Choose a status.',
        ];
    }

    /**
     * Reads a CSV import file and works out what would change. Never writes.
     *
     * @return array{rows: list<array{line: int, data: array<string, string|null>, existing: ?string}>, errors: list<array{line: int, message: string}>, create: int, update: int, unchanged: int}
     */
    public function analyse(string $path): array
    {
        $result = ['rows' => [], 'errors' => [], 'create' => 0, 'update' => 0, 'unchanged' => 0];

        $handle = fopen($path, 'r');
        if ($handle === false) {
            $result['errors'][] = ['line' => 0, 'message' => 'The file could not be read.'];

            return $result;
        }

        $header = fgetcsv($handle, escape: '\\');
        if (! $header) {
            fclose($handle);
            $result['errors'][] = ['line' => 1, 'message' => 'The file is empty. Use the template and keep the header row.'];

            return $result;
        }
        $header = array_map(fn ($h) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h))), $header);
        $missing = array_diff(['service_number', 'surname', 'first_name'], $header);
        if ($missing) {
            fclose($handle);
            $result['errors'][] = ['line' => 1, 'message' => 'Missing required column(s): '.implode(', ', $missing).'. Use the template header row.'];

            return $result;
        }
        $unknown = array_diff(array_filter($header), self::COLUMNS);
        if ($unknown) {
            $result['errors'][] = ['line' => 1, 'message' => 'Ignored unknown column(s): '.implode(', ', $unknown).'.'];
        }

        $seen = [];
        $parsed = [];
        $line = 1;
        while (($cells = fgetcsv($handle, escape: '\\')) !== false) {
            $line++;
            if ($cells === [null] || count(array_filter($cells, fn ($c) => trim((string) $c) !== '')) === 0) {
                continue; // blank line
            }
            $row = [];
            foreach ($header as $i => $col) {
                if (in_array($col, self::COLUMNS, true)) {
                    $value = trim((string) ($cells[$i] ?? ''));
                    $row[$col] = $value === '' ? null : $value; // strings only: leading zeroes survive
                }
            }
            if (array_key_exists('status', $row)) {
                $row['status'] = $row['status'] === null ? 'active' : strtolower($row['status']);
            }
            $sn = (string) ($row['service_number'] ?? '');

            $check = $row + ['status' => 'active'];
            $validator = Validator::make($check, array_merge($this->rules(), [
                'service_number' => ['required', 'string', 'regex:/^[0-9]{1,20}$/'],
            ]), $this->messages());
            if ($validator->fails()) {
                $result['errors'][] = ['line' => $line, 'message' => ($sn !== '' ? $sn.': ' : '').implode(' ', $validator->errors()->all())];

                continue;
            }
            if (isset($seen[$sn])) {
                $result['errors'][] = ['line' => $line, 'message' => $sn.': duplicate of row '.$seen[$sn].' in this file.'];

                continue;
            }
            $seen[$sn] = $line;
            $parsed[] = ['line' => $line, 'data' => $row];
        }
        fclose($handle);

        // Compare with what's stored, in batches.
        foreach (array_chunk($parsed, 500) as $chunk) {
            $existing = PersonnelRecord::whereIn('service_number', array_map(fn ($r) => $r['data']['service_number'], $chunk))
                ->get()->keyBy('service_number');
            foreach ($chunk as $row) {
                $record = $existing->get($row['data']['service_number']);
                if (! $record) {
                    $result['create']++;
                    $row['existing'] = null;
                } elseif ($this->differs($record, $row['data'])) {
                    $result['update']++;
                    $row['existing'] = $record->id;
                } else {
                    $result['unchanged']++;

                    continue;
                }
                $result['rows'][] = $row;
            }
        }

        return $result;
    }

    /**
     * Applies an analysed import inside one transaction.
     *
     * @param  array{rows: list<array{line: int, data: array<string, string|null>, existing: ?string}>}  $analysis
     * @return array{created: int, updated: int, suspended: int}
     */
    public function apply(array $analysis, User $actor): array
    {
        return DB::transaction(function () use ($analysis, $actor) {
            $counts = ['created' => 0, 'updated' => 0, 'suspended' => 0];
            $now = now();
            foreach ($analysis['rows'] as $row) {
                $data = $row['data'];
                if ($row['existing']) {
                    $record = PersonnelRecord::findOrFail($row['existing']);
                    $record->fill($data)->forceFill(['source_synced_at' => $now])->save();
                    $counts['updated']++;
                } else {
                    $record = PersonnelRecord::create($data + [
                        'status' => 'active', 'source' => 'admin', 'source_synced_at' => $now,
                    ]);
                    $counts['created']++;
                }
                if ($this->suspendAccountIfInactive($record, $actor)) {
                    $counts['suspended']++;
                }
            }

            return $counts;
        });
    }

    /** @param array<string, string|null> $data */
    private function differs(PersonnelRecord $record, array $data): bool
    {
        foreach ($data as $key => $value) {
            if ((string) $record->{$key} !== (string) $value) {
                return true;
            }
        }

        return false;
    }
}
