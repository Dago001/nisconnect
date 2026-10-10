@extends('admin.layout')
@section('title', 'Personnel records')
@section('content')
@php $me = auth()->user(); @endphp
<x-admin.page-header title="Personnel records" subtitle="The authorised personnel list that Service Numbers are checked against when officers register.">
    @if($me->hasPermission('personnel.view'))
        <a class="btn btn-ghost" href="{{ route('admin.personnel.export', request()->query()) }}"><x-admin.icon name="download" /> Export CSV</a>
    @endif
    @if($me->hasPermission('personnel.import'))
        <a class="btn btn-ghost" href="{{ route('admin.personnel.import') }}"><x-admin.icon name="upload" /> Import</a>
    @endif
    @if($me->hasPermission('personnel.manage'))
        <a class="btn" href="{{ route('admin.personnel.create') }}"><x-admin.icon name="plus" /> Add record</a>
    @endif
</x-admin.page-header>

@include('admin.personnel.provider-notice')

<div class="card">
    <form method="GET" action="{{ route('admin.personnel.index') }}" class="filters" role="search">
        <div class="f" style="max-width:320px"><label for="q">Search</label>
            <input id="q" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Name or Service No."></div>
        <div class="f"><label for="status">Status</label>
            <select id="status" name="status" data-autosubmit>
                <option value="">Any status</option>
                @foreach($statuses as $s)<option value="{{ $s }}" @selected(($filters['status'] ?? '') === $s)>{{ ucfirst($s) }}</option>@endforeach
            </select></div>
        @foreach(['rank' => 'Rank', 'directorate' => 'Directorate', 'command' => 'Command'] as $col => $label)
            <div class="f"><label for="{{ $col }}">{{ $label }}</label>
                <select id="{{ $col }}" name="{{ $col }}" data-autosubmit>
                    <option value="">Any {{ strtolower($label) }}</option>
                    @foreach($options[$col] as $v)<option value="{{ $v }}" @selected(($filters[$col] ?? '') === $v)>{{ $v }}</option>@endforeach
                </select></div>
        @endforeach
        <div class="f"><label for="registered">Registered?</label>
            <select id="registered" name="registered" data-autosubmit>
                <option value="">Either</option>
                <option value="yes" @selected(($filters['registered'] ?? '') === 'yes')>Has an account</option>
                <option value="no" @selected(($filters['registered'] ?? '') === 'no')>Not registered</option>
            </select></div>
        <div><button class="btn btn-ghost" type="submit"><x-admin.icon name="search" /> Filter</button>
            @if(array_filter($filters))<a class="btn btn-ghost" href="{{ route('admin.personnel.index') }}">Clear</a>@endif</div>
    </form>
    <div class="card-head"><h2>{{ number_format($records->total()) }} {{ \Illuminate\Support\Str::plural('record', $records->total()) }}</h2></div>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Officer</th><th>Service No.</th><th>Rank</th><th>Posting</th><th>Status</th><th>Account</th><th></th></tr></thead>
        <tbody>
        @forelse($records as $r)
            <tr>
                <td><div class="cell-title">{{ $r->surname }}, {{ $r->first_name }} {{ $r->other_name }}</div>
                    <div class="cell-sub">{{ $r->official_email ?? '—' }}</div></td>
                <td class="mono nowrap">{{ $r->service_number }}</td>
                <td>{{ $r->rank ?? '—' }}</td>
                <td><div>{{ $r->directorate ?? '—' }}</div><div class="cell-sub">{{ collect([$r->command, $r->formation])->filter()->implode(' · ') }}</div></td>
                <td><x-admin.badge :value="$r->status" /></td>
                <td>@if($r->registered)<x-admin.badge value="registered" tone="success">Registered</x-admin.badge>@else<span class="muted small">Not yet</span>@endif
                    @if($r->source === 'admin')<div class="cell-sub">Added by admin</div>@endif</td>
                <td class="row-actions">
                    @if($me->hasPermission('personnel.manage'))
                        <a class="btn btn-sm btn-ghost" href="{{ route('admin.personnel.edit', $r) }}"><x-admin.icon name="edit" /> Edit</a>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="7"><x-admin.empty icon="id" title="No personnel records found">
                @if(array_filter($filters)) Try a different search or clear the filters. @else Import a CSV or add records one at a time. @endif
            </x-admin.empty></td></tr>
        @endforelse
        </tbody>
    </table></div>
    {{ $records->links() }}
</div>
@endsection
