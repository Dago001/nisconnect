@extends('admin.layout')
@section('title', 'Audit log')
@section('content')
@php $me = auth()->user(); $active = array_filter($filters); @endphp
<x-admin.page-header title="Audit log" subtitle="Every administrative and account action, in order. This record is append-only and can't be edited or deleted.">
    @if($me->hasPermission('audit.export'))
        <a class="btn btn-ghost" href="{{ route('admin.audit.export', $active) }}"><x-admin.icon name="download" /> Export CSV</a>
    @endif
</x-admin.page-header>

<div class="card">
    <form method="GET" class="filters" action="{{ route('admin.audit.index') }}">
        <div class="f">
            <label for="action">Action</label>
            <input id="action" type="search" name="action" value="{{ $filters['action'] ?? '' }}" placeholder="e.g. login or officer.">
        </div>
        <div class="f">
            <label for="actor">Actor Service No.</label>
            <input id="actor" name="actor" inputmode="numeric" pattern="[0-9]*" value="{{ $filters['actor'] ?? '' }}" class="mono">
        </div>
        <div class="f">
            <label for="result">Result</label>
            <select id="result" name="result" data-autosubmit>
                <option value="">Any</option>
                @foreach($results as $r)<option value="{{ $r }}" @selected(($filters['result'] ?? '') === $r)>{{ ucfirst($r) }}</option>@endforeach
            </select>
        </div>
        <div class="f">
            <label for="resource_type">Resource</label>
            <select id="resource_type" name="resource_type" data-autosubmit>
                <option value="">Any</option>
                @foreach($resourceTypes as $t)<option value="{{ $t }}" @selected(($filters['resource_type'] ?? '') === $t)>{{ str_replace('_', ' ', $t) }}</option>@endforeach
            </select>
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
        @if($active)<a class="btn btn-sm btn-ghost" href="{{ route('admin.audit.index') }}">Clear</a>@endif
    </form>

    <div class="table-wrap"><table class="table">
        <thead><tr><th>When</th><th>Action</th><th>Actor</th><th>Resource</th><th>Result</th><th>IP</th><th>Details</th></tr></thead>
        <tbody>
        @forelse($logs as $log)
            @php $actor = $actors[$log->actor_id] ?? null; @endphp
            <tr>
                <td class="nowrap" title="{{ $log->created_at?->diffForHumans() }}">{{ $log->created_at?->format('d M Y, H:i:s') }}</td>
                <td class="mono">{{ $log->action }}</td>
                <td>
                    @if($actor)
                        <div class="cell-title">{{ $actor->display_name }}</div>
                        <div class="cell-sub mono">{{ $actor->service_number }}</div>
                    @else
                        <span class="muted">System</span>
                    @endif
                </td>
                <td>
                    @if($log->resource_type)
                        <div class="cell-title">{{ str_replace('_', ' ', $log->resource_type) }}</div>
                        @if($log->resource_id)<div class="cell-sub mono" title="{{ $log->resource_id }}">{{ \Illuminate\Support\Str::limit($log->resource_id, 13) }}</div>@endif
                    @else
                        <span class="muted">—</span>
                    @endif
                </td>
                <td><x-admin.badge :value="$log->result" /></td>
                <td class="mono small nowrap">{{ $log->ip ?? '—' }}</td>
                <td>
                    @if($log->metadata)
                        <details><summary class="small">View</summary>
                            <pre class="mono small" style="white-space:pre-wrap;word-break:break-word;max-width:360px;margin:6px 0 0">{{ json_encode($log->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                        </details>
                    @else
                        <span class="muted">—</span>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="7"><x-admin.empty icon="list" title="No audit entries found">Try widening the filters.</x-admin.empty></td></tr>
        @endforelse
        </tbody>
    </table></div>
    {{ $logs->links() }}
</div>
@endsection
