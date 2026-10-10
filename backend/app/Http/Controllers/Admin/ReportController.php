<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Group;
use App\Models\Message;
use App\Models\Report;
use App\Models\User;
use App\Services\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Moderation queue for reports raised by officers in the app.
 * Message content is never shown in the list; only on the detail page.
 */
class ReportController extends Controller
{
    public const STATUSES = ['open', 'reviewing', 'actioned', 'dismissed'];

    public const TARGET_TYPES = ['user' => 'Officer', 'message' => 'Message', 'group' => 'Group'];

    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', 'in:all,'.implode(',', self::STATUSES)],
            'target_type' => ['nullable', 'in:'.implode(',', array_keys(self::TARGET_TYPES))],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $status = $filters['status'] ?? 'open';

        $base = Report::query()
            ->when($filters['target_type'] ?? null, fn ($q, $t) => $q->where('target_type', $t))
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(function ($q) use ($term) {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $q->where('reason', 'ilike', $like)->orWhere('details', 'ilike', $like);
            }));

        $counts = (clone $base)->select('status', DB::raw('count(*) as c'))->groupBy('status')->pluck('c', 'status');
        $counts = collect(self::STATUSES)->mapWithKeys(fn ($s) => [$s => (int) ($counts[$s] ?? 0)])->all();
        $counts['all'] = array_sum($counts);

        $reports = (clone $base)
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        $targets = $this->resolveTargets($reports->getCollection());
        $reporters = User::withTrashed()->whereIn('id', $reports->pluck('reporter_id')->unique())
            ->get(['id', 'display_name', 'service_number'])->keyBy('id');

        return view('admin.reports.index', [
            'reports' => $reports,
            'status' => $status,
            'filters' => $filters,
            'counts' => $counts,
            'targets' => $targets,
            'reporters' => $reporters,
            'targetTypes' => self::TARGET_TYPES,
        ]);
    }

    public function show(Request $request, Report $report): View
    {
        $target = $this->resolveTargets(collect([$report]))[$report->id] ?? null;
        $reporter = User::withTrashed()->find($report->reporter_id);
        $reviewer = $report->reviewed_by ? User::withTrashed()->find($report->reviewed_by) : null;

        $related = Report::where('target_type', $report->target_type)
            ->where('target_id', $report->target_id)
            ->where('id', '!=', $report->id)
            ->orderByDesc('created_at')->limit(50)->get();
        $relatedReporters = User::withTrashed()->whereIn('id', $related->pluck('reporter_id')->unique())
            ->pluck('display_name', 'id');

        $history = AuditLog::where('resource_type', 'report')->where('resource_id', $report->id)
            ->orderByDesc('created_at')->get();
        $historyActors = User::withTrashed()->whereIn('id', $history->pluck('actor_id')->filter()->unique())
            ->pluck('display_name', 'id');

        $me = $request->user();
        $canSuspend = $report->target_type === 'user'
            && $me->hasPermission('officers.manage')
            && ($target['user'] ?? null) instanceof User
            && $target['user']->id !== $me->id
            && ($me->isSuperAdmin() || ! $target['user']->isSuperAdmin());

        return view('admin.reports.show', compact(
            'report', 'target', 'reporter', 'reviewer', 'related', 'relatedReporters',
            'history', 'historyActors', 'canSuspend',
        ) + ['targetTypes' => self::TARGET_TYPES]);
    }

    public function action(Request $request, Report $report): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:reviewing,actioned,dismissed'],
            'resolution_note' => ['nullable', 'string', 'max:2000'],
            'suspend_officer' => ['nullable', 'boolean'],
        ]);
        $me = $request->user();
        $previous = $report->status;

        $report->forceFill([
            'status' => $data['status'],
            'resolution_note' => $data['resolution_note'] ?? $report->resolution_note,
            'reviewed_by' => $me->id,
            'reviewed_at' => Carbon::now(),
        ])->save();

        $this->audit->log('report.reviewed', actorId: $me->id, resourceType: 'report', resourceId: $report->id,
            metadata: [
                'from' => $previous,
                'to' => $data['status'],
                'target_type' => $report->target_type,
                'note_added' => filled($data['resolution_note'] ?? null),
            ]);

        $message = 'Report updated.';
        if (! empty($data['suspend_officer'])) {
            $message = $this->suspendTarget($request, $report) ?? $message;
        }

        return redirect()->route('admin.reports.show', $report)->with('status', $message);
    }

    /** Suspends the officer a report is about. Returns a status message, or null if nothing changed. */
    private function suspendTarget(Request $request, Report $report): ?string
    {
        $me = $request->user();
        $target = $report->target_type === 'user' ? User::find($report->target_id) : null;

        if (! $target || ! $me->hasPermission('officers.manage')) {
            abort(403, 'You are not allowed to suspend this officer.');
        }
        if ($target->id === $me->id) {
            return 'Report updated. You cannot suspend your own account.';
        }
        if ($target->isSuperAdmin() && ! $me->isSuperAdmin()) {
            abort(403, 'Only a Super Administrator can suspend another Super Administrator.');
        }
        if ($target->account_state === User::STATE_SUSPENDED) {
            return 'Report updated. The officer was already suspended.';
        }

        $from = $target->account_state;
        $target->forceFill(['account_state' => User::STATE_SUSPENDED, 'presence' => 'offline'])->save();
        $target->tokens()->delete();

        $this->audit->log('officer.suspended', actorId: $me->id, resourceType: 'user', resourceId: $target->id,
            metadata: ['from' => $from, 'via_report' => $report->id]);
        $this->audit->security('account_suspended', userId: $target->id, severity: 'warning',
            metadata: ['by' => $me->id, 'via_report' => $report->id]);

        return 'Report updated and the officer has been suspended and signed out.';
    }

    /**
     * Resolves each report's target to a display summary.
     *
     * @param  Collection<int, Report>  $reports
     * @return array<string, array<string, mixed>> keyed by report id
     */
    private function resolveTargets(Collection $reports): array
    {
        $ids = fn (string $type) => $reports->where('target_type', $type)->pluck('target_id')->unique()->values();

        $users = User::withTrashed()->whereIn('id', $ids('user'))->get()->keyBy('id');
        $messages = Message::withTrashed()->with(['conversation:id,type,title', 'sender:id,display_name,service_number'])->whereIn('id', $ids('message'))->get()->keyBy('id');
        $groups = Group::withTrashed()->whereIn('id', $ids('group'))->get()->keyBy('id');

        $out = [];
        foreach ($reports as $r) {
            $out[$r->id] = match ($r->target_type) {
                'user' => ['user' => $users[$r->target_id] ?? null],
                'message' => ['message' => $messages[$r->target_id] ?? null],
                'group' => ['group' => $groups[$r->target_id] ?? null],
                default => [],
            };
        }

        return $out;
    }
}
