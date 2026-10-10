@extends('admin.layout')
@section('title', 'Report')
@section('inline-errors', '1')
@section('content')
<x-admin.page-header title="Report about {{ strtolower($targetTypes[$report->target_type] ?? $report->target_type) }}"
    subtitle="Raised {{ $report->created_at?->format('d M Y, H:i') }} ({{ $report->created_at?->diffForHumans() }})"
    :crumbs="['Reports' => route('admin.reports.index')]">
    <x-admin.badge :value="$report->status" />
</x-admin.page-header>

<div class="grid grid-sidebar">
    <div class="stack">
        <div class="card">
            <div class="card-head"><h2>Details</h2></div>
            <div class="card-body">
                <dl class="dl">
                    <dt>Reason</dt><dd>{{ $report->reason }}</dd>
                    <dt>Reporter's description</dt><dd style="white-space:pre-line">{{ $report->details ?: '—' }}</dd>
                    <dt>Reported by</dt>
                    <dd>
                        @if($reporter)
                            {{ $reporter->display_name }} · <span class="mono">{{ $reporter->service_number }}</span>
                            @if(auth()->user()->hasPermission('officers.view') && ! $reporter->trashed())
                                · <a href="{{ route('admin.officers.show', $reporter) }}">View officer</a>
                            @endif
                        @else
                            <span class="muted">Unknown</span>
                        @endif
                    </dd>
                    <dt>Status</dt><dd><x-admin.badge :value="$report->status" /></dd>
                    @if($report->reviewed_at)
                        <dt>Last reviewed</dt><dd>{{ $report->reviewed_at->format('d M Y, H:i') }} by {{ $reviewer?->display_name ?? 'unknown' }}</dd>
                    @endif
                    @if($report->resolution_note)
                        <dt>Resolution note</dt><dd style="white-space:pre-line">{{ $report->resolution_note }}</dd>
                    @endif
                </dl>
            </div>
        </div>

        <div class="card">
            <div class="card-head"><h2>What was reported</h2></div>
            <div class="card-body">
                @include('admin.reports._target', ['report' => $report, 'target' => $target ?? []])

                @if($report->target_type === 'user' && ($u = $target['user'] ?? null))
                    <dl class="dl" style="margin-top:12px">
                        <dt>Account state</dt><dd><x-admin.badge :value="$u->account_state" /></dd>
                        <dt>Rank</dt><dd>{{ $u->personnelRecord?->rank ?? '—' }}</dd>
                    </dl>
                @elseif($report->target_type === 'message' && ($m = $target['message'] ?? null))
                    <div class="alert alert-warning" style="margin:12px 0 8px">
                        <strong>Reported message content (confidential).</strong>
                        Shown only to administrators reviewing reports, for moderation. Don't copy or share it.
                    </div>
                    <details>
                        <summary>Show the reported message</summary>
                        <div class="card card-body" style="margin-top:8px;white-space:pre-line">
                            @if($m->trashed())<div class="muted small">This message has been deleted.</div>@endif
                            @if(filled($m->body)){{ $m->body }}@else<span class="muted">No text — {{ $m->type }} message.</span>@endif
                        </div>
                        <div class="muted small" style="margin-top:6px">Sent {{ $m->created_at?->format('d M Y, H:i') }}</div>
                    </details>
                @elseif($report->target_type === 'group' && ($g = $target['group'] ?? null))
                    <dl class="dl" style="margin-top:12px">
                        <dt>Description</dt><dd>{{ $g->description ?: '—' }}</dd>
                        <dt>Type</dt><dd>{{ ucfirst($g->type) }}</dd>
                    </dl>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-head"><h2>Other reports about the same {{ strtolower($targetTypes[$report->target_type] ?? 'item') }}</h2><span class="muted small">{{ $related->count() }}</span></div>
            <div class="table-wrap"><table class="table">
                <thead><tr><th>Reported by</th><th>Reason</th><th>Status</th><th>When</th></tr></thead>
                <tbody>
                @forelse($related as $r)
                    <tr>
                        <td>{{ $relatedReporters[$r->reporter_id] ?? 'Unknown' }}</td>
                        <td><a href="{{ route('admin.reports.show', $r) }}">{{ \Illuminate\Support\Str::limit($r->reason, 80) }}</a></td>
                        <td><x-admin.badge :value="$r->status" /></td>
                        <td class="nowrap muted small">{{ $r->created_at?->diffForHumans() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4"><x-admin.empty icon="flag" title="No other reports" /></td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>

        <div class="card">
            <div class="card-head"><h2>Review history</h2></div>
            <div class="table-wrap"><table class="table">
                <thead><tr><th>When</th><th>Administrator</th><th>Change</th></tr></thead>
                <tbody>
                @forelse($history as $log)
                    <tr>
                        <td class="nowrap">{{ $log->created_at?->format('d M Y, H:i') }}</td>
                        <td>{{ $historyActors[$log->actor_id] ?? 'System' }}</td>
                        <td>
                            @if(isset($log->metadata['from'], $log->metadata['to']))
                                {{ ucfirst($log->metadata['from']) }} → {{ ucfirst($log->metadata['to']) }}
                            @else
                                <span class="mono">{{ $log->action }}</span>
                            @endif
                            @if(! empty($log->metadata['note_added']))<span class="muted small">· note added</span>@endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3"><x-admin.empty icon="list" title="Not reviewed yet" /></td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
    </div>

    <div class="stack">
        <div class="card card-body">
            <h2>Take action</h2>
            <form method="POST" action="{{ route('admin.reports.action', $report) }}">
                @csrf
                <div class="field">
                    <label for="status">New status</label>
                    <select id="status" name="status" required>
                        @foreach(['reviewing' => 'Reviewing — I\'m looking into it', 'actioned' => 'Actioned — action was taken', 'dismissed' => 'Dismissed — no action needed'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', $report->status === 'open' ? 'reviewing' : $report->status) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('status')<div class="error">{{ $message }}</div>@enderror
                </div>
                <div class="field">
                    <label for="resolution_note">Resolution note</label>
                    <textarea id="resolution_note" name="resolution_note" rows="4" maxlength="2000">{{ old('resolution_note', $report->resolution_note) }}</textarea>
                    <div class="help">Internal note for other administrators. Not shown to the reporter.</div>
                    @error('resolution_note')<div class="error">{{ $message }}</div>@enderror
                </div>
                @if($canSuspend)
                    <div class="field">
                        <label class="check"><input type="checkbox" name="suspend_officer" value="1" @checked(old('suspend_officer'))>
                            <span>Suspend the reported officer<span class="help" style="display:block">Signs them out of every device. You can reactivate them later from their officer page.</span></span>
                        </label>
                    </div>
                @endif
                <button class="btn" type="submit"><x-admin.icon name="check" /> Save</button>
            </form>
        </div>
    </div>
</div>
@endsection
