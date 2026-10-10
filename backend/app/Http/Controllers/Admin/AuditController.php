<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Services\Support\AuditLogger;
use App\Support\CsvExport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Read-only views over the append-only audit trail and security events.
 * There is deliberately no edit or delete here.
 */
class AuditController extends Controller
{
    public const RESULTS = ['success', 'failure', 'denied'];

    public const SEVERITIES = ['info', 'warning', 'critical'];

    public function __construct(private readonly AuditLogger $audit) {}

    // --- Audit log -----------------------------------------------------------

    public function auditLogs(Request $request): View
    {
        $filters = $this->validateAuditFilters($request);
        $logs = $this->auditQuery($filters)->orderByDesc('created_at')->orderByDesc('id')
            ->paginate(50)->withQueryString();
        $actors = $this->usersById($logs->pluck('actor_id'));
        $resourceTypes = AuditLog::query()->whereNotNull('resource_type')->distinct()->orderBy('resource_type')->pluck('resource_type');

        return view('admin.audit.index', [
            'logs' => $logs,
            'actors' => $actors,
            'filters' => $filters,
            'resourceTypes' => $resourceTypes,
            'results' => self::RESULTS,
        ]);
    }

    public function exportAudit(Request $request): StreamedResponse
    {
        $filters = $this->validateAuditFilters($request);
        $this->audit->log('audit.exported', actorId: $request->user()->id, resourceType: 'audit_log',
            metadata: ['filters' => array_filter($filters)]);

        $query = $this->auditQuery($filters)->orderByDesc('created_at');
        $rows = (function () use ($query) {
            $names = [];
            foreach ($query->cursor() as $log) {
                $actor = $log->actor_id ? ($names[$log->actor_id] ??= User::withTrashed()->find($log->actor_id, ['id', 'display_name', 'service_number'])) : null;
                yield [
                    $log->created_at?->toIso8601String(), $log->action, $log->result,
                    $actor?->display_name, $actor?->service_number,
                    $log->resource_type, $log->resource_id, $log->ip, $log->device_id,
                    $log->metadata ? json_encode($log->metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '',
                ];
            }
        })();

        return CsvExport::download('audit-log-'.now()->format('Ymd-His').'.csv',
            ['When (UTC)', 'Action', 'Result', 'Actor', 'Actor Service No.', 'Resource type', 'Resource ID', 'IP', 'Device ID', 'Details'],
            $rows);
    }

    // --- Security events -----------------------------------------------------

    public function securityEvents(Request $request): View
    {
        $filters = $this->validateSecurityFilters($request);
        $events = $this->securityQuery($filters)->orderByDesc('created_at')->orderByDesc('id')
            ->paginate(50)->withQueryString();
        $users = $this->usersById($events->pluck('user_id'));
        $eventNames = SecurityEvent::query()->distinct()->orderBy('event')->pluck('event');

        return view('admin.security.index', [
            'events' => $events,
            'users' => $users,
            'filters' => $filters,
            'eventNames' => $eventNames,
            'severities' => self::SEVERITIES,
        ]);
    }

    public function exportSecurity(Request $request): StreamedResponse
    {
        $filters = $this->validateSecurityFilters($request);
        $this->audit->log('security_events.exported', actorId: $request->user()->id, resourceType: 'security_event',
            metadata: ['filters' => array_filter($filters)]);

        $query = $this->securityQuery($filters)->orderByDesc('created_at');
        $rows = (function () use ($query) {
            $names = [];
            foreach ($query->cursor() as $e) {
                $user = $e->user_id ? ($names[$e->user_id] ??= User::withTrashed()->find($e->user_id, ['id', 'display_name', 'service_number'])) : null;
                yield [
                    $e->created_at?->toIso8601String(), $e->severity, $e->event,
                    $user?->display_name, $user?->service_number, $e->ip, $e->device_id,
                    $e->metadata ? json_encode($e->metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '',
                ];
            }
        })();

        return CsvExport::download('security-events-'.now()->format('Ymd-His').'.csv',
            ['When (UTC)', 'Severity', 'Event', 'Officer', 'Service No.', 'IP', 'Device ID', 'Details'],
            $rows);
    }

    // --- Queries -------------------------------------------------------------

    /** @return array<string, mixed> */
    private function validateAuditFilters(Request $request): array
    {
        return $request->validate([
            'action' => ['nullable', 'string', 'max:100'],
            'actor' => ['nullable', 'string', 'regex:/^[0-9]{1,20}$/'],
            'result' => ['nullable', 'in:'.implode(',', self::RESULTS)],
            'resource_type' => ['nullable', 'string', 'max:60'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ], [
            'actor.regex' => 'Service Numbers contain digits only.',
            'to.after_or_equal' => 'The end date must be on or after the start date.',
        ]);
    }

    /** @return array<string, mixed> */
    private function validateSecurityFilters(Request $request): array
    {
        return $request->validate([
            'severity' => ['nullable', 'in:'.implode(',', self::SEVERITIES)],
            'event' => ['nullable', 'string', 'max:100'],
            'officer' => ['nullable', 'string', 'regex:/^[0-9]{1,20}$/'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ], [
            'officer.regex' => 'Service Numbers contain digits only.',
            'to.after_or_equal' => 'The end date must be on or after the start date.',
        ]);
    }

    /** @param array<string, mixed> $f */
    private function auditQuery(array $f): Builder
    {
        $q = AuditLog::query();
        if (filled($f['action'] ?? null)) {
            $q->where('action', 'ilike', '%'.$this->escapeLike($f['action']).'%');
        }
        if (filled($f['actor'] ?? null)) {
            $q->whereIn('actor_id', User::withTrashed()->where('service_number', $f['actor'])->select('id'));
        }
        if (filled($f['result'] ?? null)) {
            $q->where('result', $f['result']);
        }
        if (filled($f['resource_type'] ?? null)) {
            $q->where('resource_type', $f['resource_type']);
        }

        return $this->dateRange($q, $f);
    }

    /** @param array<string, mixed> $f */
    private function securityQuery(array $f): Builder
    {
        $q = SecurityEvent::query();
        if (filled($f['severity'] ?? null)) {
            $q->where('severity', $f['severity']);
        }
        if (filled($f['event'] ?? null)) {
            $q->where('event', 'ilike', '%'.$this->escapeLike($f['event']).'%');
        }
        if (filled($f['officer'] ?? null)) {
            $q->whereIn('user_id', User::withTrashed()->where('service_number', $f['officer'])->select('id'));
        }

        return $this->dateRange($q, $f);
    }

    /** @param array<string, mixed> $f */
    private function dateRange(Builder $q, array $f): Builder
    {
        if (filled($f['from'] ?? null)) {
            $q->where('created_at', '>=', Carbon::createFromFormat('Y-m-d', $f['from'])->startOfDay());
        }
        if (filled($f['to'] ?? null)) {
            $q->where('created_at', '<=', Carbon::createFromFormat('Y-m-d', $f['to'])->endOfDay());
        }

        return $q;
    }

    private function escapeLike(string $term): string
    {
        return addcslashes($term, '%_\\');
    }

    /** @return Collection<string, User> */
    private function usersById($ids)
    {
        return User::withTrashed()->whereIn('id', collect($ids)->filter()->unique()->values())
            ->get(['id', 'display_name', 'service_number'])->keyBy('id');
    }
}
