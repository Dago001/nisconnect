{{-- One-line summary of a report's target. Never shows message content. Expects $report, $target. --}}
@php $canViewOfficers = auth()->user()->hasPermission('officers.view'); @endphp
@if($report->target_type === 'user')
    @if($u = $target['user'] ?? null)
        <div class="cell-title">
            @if($canViewOfficers && ! $u->trashed())<a href="{{ route('admin.officers.show', $u) }}">{{ $u->display_name }}</a>@else{{ $u->display_name }}@endif
        </div>
        <div class="cell-sub">Officer · Service No. <span class="mono">{{ $u->service_number }}</span></div>
    @else
        <div class="cell-title muted">Deleted officer</div>
    @endif
@elseif($report->target_type === 'message')
    @if($m = $target['message'] ?? null)
        <div class="cell-title">Message in {{ $m->conversation?->type === 'direct' ? 'a direct' : 'a '.($m->conversation?->type ?? '') }} conversation</div>
        <div class="cell-sub">
            @if($m->conversation?->title){{ $m->conversation->title }} · @endif
            Sent by {{ $m->sender?->display_name ?? 'unknown' }}@if($m->sender) (<span class="mono">{{ $m->sender->service_number }}</span>)@endif
            @if($m->trashed()) · deleted @endif
        </div>
    @else
        <div class="cell-title muted">Message no longer available</div>
    @endif
@elseif($report->target_type === 'group')
    @if($g = $target['group'] ?? null)
        <div class="cell-title">
            @if(auth()->user()->hasPermission('groups.manage') && ! $g->trashed())<a href="{{ route('admin.groups.show', $g) }}">{{ $g->name }}</a>@else{{ $g->name }}@endif
        </div>
        <div class="cell-sub">Group @if($g->trashed()) · deleted @endif</div>
    @else
        <div class="cell-title muted">Deleted group</div>
    @endif
@else
    <span class="muted">{{ $report->target_type }}</span>
@endif
