@extends('admin.layout')
@section('title', 'Security events')
@section('content')
@php $me = auth()->user(); $active = array_filter($filters); @endphp
<x-admin.page-header title="Security events" subtitle="Sign-in failures, lockouts, new devices and other security signals. This record is append-only.">
    @if($me->hasPermission('audit.export'))
        <a class="btn btn-ghost" href="{{ route('admin.security.export', $active) }}"><x-admin.icon name="download" /> Export CSV</a>
    @endif
</x-admin.page-header>

<div class="card">
    <form method="GET" class="filters" action="{{ route('admin.security.index') }}">
        <div class="f">
            <label for="severity">Severity</label>
            <select id="severity" name="severity" data-autosubmit>
                <option value="">Any</option>
                @foreach($severities as $s)<option value="{{ $s }}" @selected(($filters['severity'] ?? '') === $s)>{{ ucfirst($s) }}</option>@endforeach
            </select>
        </div>
        <div class="f">
            <label for="event">Event</label>
            <input id="event" type="search" name="event" value="{{ $filters['event'] ?? '' }}" list="event-names" placeholder="e.g. failed_login">
            <datalist id="event-names">@foreach($eventNames as $n)<option value="{{ $n }}">@endforeach</datalist>
        </div>
        <div class="f">
            <label for="officer">Officer Service No.</label>
            <input id="officer" name="officer" inputmode="numeric" pattern="[0-9]*" value="{{ $filters['officer'] ?? '' }}" class="mono">
        </div>
        <div class="f">
            <label for="from">From</label>
            <input id="from" type="date" name="from" value="{{ $filters['from'] ?? '' }}">
        </div>
        <div class="f">
            <label for="to">To</label>
            <input id="to" type="date" name="to" value="{{ $filters['to'] ?? '' }}">
        </div>
        <button class="btn btn-sm" type="submit"><x-admin.icon name="search" /> Filter</button>
        @if($active)<a class="btn btn-sm btn-ghost" href="{{ route('admin.security.index') }}">Clear</a>@endif
    </form>

    <div class="table-wrap"><table class="table">
        <thead><tr><th>When</th><th>Severity</th><th>Event</th><th>Officer</th><th>IP</th><th>Details</th></tr></thead>
        <tbody>
        @forelse($events as $e)
            @php $u = $users[$e->user_id] ?? null; @endphp
            <tr>
                <td class="nowrap" title="{{ $e->created_at?->diffForHumans() }}">{{ $e->created_at?->format('d M Y, H:i:s') }}</td>
                <td><x-admin.badge :value="$e->severity" /></td>
                <td class="mono">{{ $e->event }}</td>
                <td>
                    @if($u)
                        <div class="cell-title">
                            @if($me->hasPermission('officers.view') && ! $u->trashed())<a href="{{ route('admin.officers.show', $u) }}">{{ $u->display_name }}</a>@else{{ $u->display_name }}@endif
                        </div>
                        <div class="cell-sub mono">{{ $u->service_number }}</div>
                    @else
                        <span class="muted">—</span>
                    @endif
                </td>
                <td class="mono small nowrap">{{ $e->ip ?? '—' }}</td>
                <td>
                    @if($e->metadata)
                        <details><summary class="small">View</summary>
                            <pre class="mono small" style="white-space:pre-wrap;word-break:break-word;max-width:360px;margin:6px 0 0">{{ json_encode($e->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                        </details>
                    @else
                        <span class="muted">—</span>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="6"><x-admin.empty icon="shield" title="No security events found">Try widening the filters.</x-admin.empty></td></tr>
        @endforelse
        </tbody>
    </table></div>
    {{ $events->links() }}
</div>
@endsection
