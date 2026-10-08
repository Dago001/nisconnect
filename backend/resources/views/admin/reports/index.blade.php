@extends('admin.layout')
@section('title', 'Reports')
@section('content')
<x-admin.page-header title="Reports" subtitle="Reports raised by officers about other officers, messages and groups." />

<nav class="tabs" aria-label="Report status">
    @foreach(['open' => 'Open', 'reviewing' => 'Reviewing', 'actioned' => 'Actioned', 'dismissed' => 'Dismissed', 'all' => 'All'] as $key => $label)
        <a href="{{ route('admin.reports.index', array_filter(['status' => $key, 'target_type' => $filters['target_type'] ?? null, 'q' => $filters['q'] ?? null])) }}"
           class="{{ $status === $key ? 'active' : '' }}" @if($status === $key) aria-current="page" @endif>
            {{ $label }} <span class="badge">{{ number_format($counts[$key]) }}</span>
        </a>
    @endforeach
</nav>

<div class="card">
    <form method="GET" class="filters" action="{{ route('admin.reports.index') }}">
        <input type="hidden" name="status" value="{{ $status }}">
        <div class="f">
            <label for="target_type">About</label>
            <select id="target_type" name="target_type" data-autosubmit>
                <option value="">Anything</option>
                @foreach($targetTypes as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['target_type'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="f">
            <label for="q">Reason</label>
            <input id="q" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search reasons">
        </div>
        <button class="btn btn-sm" type="submit"><x-admin.icon name="search" /> Filter</button>
        @if(($filters['target_type'] ?? null) || ($filters['q'] ?? null))
            <a class="btn btn-sm btn-ghost" href="{{ route('admin.reports.index', ['status' => $status]) }}">Clear</a>
        @endif
    </form>

    <div class="table-wrap"><table class="table">
        <thead><tr><th>Reported by</th><th>About</th><th>Reason</th><th>Status</th><th>Age</th><th></th></tr></thead>
        <tbody>
        @forelse($reports as $report)
            @php $reporter = $reporters[$report->reporter_id] ?? null; @endphp
            <tr>
                <td>
                    <div class="cell-title">{{ $reporter?->display_name ?? 'Unknown' }}</div>
                    @if($reporter)<div class="cell-sub mono">{{ $reporter->service_number }}</div>@endif
                </td>
                <td>@include('admin.reports._target', ['report' => $report, 'target' => $targets[$report->id] ?? []])</td>
                <td>{{ \Illuminate\Support\Str::limit($report->reason, 80) }}</td>
                <td><x-admin.badge :value="$report->status" /></td>
                <td class="nowrap muted small" title="{{ $report->created_at?->format('d M Y, H:i') }}">{{ $report->created_at?->diffForHumans(short: true) }}</td>
                <td class="row-actions"><a class="btn btn-sm btn-ghost" href="{{ route('admin.reports.show', $report) }}"><x-admin.icon name="eye" /> Review</a></td>
            </tr>
        @empty
            <tr><td colspan="6"><x-admin.empty icon="flag" title="No reports here">Nothing matches this view. Reports raised in the app appear here.</x-admin.empty></td></tr>
        @endforelse
        </tbody>
    </table></div>
    {{ $reports->links() }}
</div>
@endsection
