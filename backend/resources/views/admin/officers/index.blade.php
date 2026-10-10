@extends('admin.layout')
@section('title', 'Officers')
@section('content')
@php $me = auth()->user(); @endphp
<x-admin.page-header title="Officers" subtitle="Everyone who has registered on NISconnect with a verified Service Number.">
    @if($me->hasPermission('officers.export'))
        <a class="btn btn-ghost" href="{{ route('admin.officers.export', request()->query()) }}"><x-admin.icon name="download" /> Export CSV</a>
    @endif
</x-admin.page-header>

<div class="card">
    <form method="GET" action="{{ route('admin.officers.index') }}" class="filters" role="search">
        <div class="f" style="max-width:320px">
            <label for="q">Search</label>
            <input type="search" id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Name or Service Number">
        </div>
        <div class="f">
            <label for="state">Account state</label>
            <select id="state" name="state" data-autosubmit>
                <option value="">All states</option>
                @foreach($states as $s)<option value="{{ $s }}" @selected(($filters['state'] ?? '') === $s)>{{ ucfirst($s) }}</option>@endforeach
            </select>
        </div>
        <div class="f">
            <label for="role">Role</label>
            <select id="role" name="role" data-autosubmit>
                <option value="">All roles</option>
                @foreach($roles as $name => $label)<option value="{{ $name }}" @selected(($filters['role'] ?? '') === $name)>{{ $label }}</option>@endforeach
            </select>
        </div>
        <div class="f">
            <label for="command">Command</label>
            <select id="command" name="command" data-autosubmit>
                <option value="">All commands</option>
                @foreach($commands as $c)<option value="{{ $c }}" @selected(($filters['command'] ?? '') === $c)>{{ $c }}</option>@endforeach
            </select>
        </div>
        <div class="f">
            <label for="directorate">Directorate</label>
            <select id="directorate" name="directorate" data-autosubmit>
                <option value="">All directorates</option>
                @foreach($directorates as $d)<option value="{{ $d }}" @selected(($filters['directorate'] ?? '') === $d)>{{ $d }}</option>@endforeach
            </select>
        </div>
        <div class="f">
            <label for="sort">Sort by</label>
            <select id="sort" name="sort" data-autosubmit>
                <option value="newest" @selected(($filters['sort'] ?? 'newest') !== 'name')>Newest first</option>
                <option value="name" @selected(($filters['sort'] ?? '') === 'name')>Name (A–Z)</option>
            </select>
        </div>
        <div class="actions">
            <button class="btn" type="submit"><x-admin.icon name="search" /> Search</button>
            @if(array_filter($filters))<a class="btn btn-ghost" href="{{ route('admin.officers.index') }}">Clear</a>@endif
        </div>
    </form>

    <div class="table-wrap"><table class="table">
        <thead><tr><th>Officer</th><th>Service No.</th><th>Command</th><th>State</th><th>Devices</th><th>Last seen</th><th>Joined</th><th></th></tr></thead>
        <tbody>
        @forelse($officers as $o)
            <tr>
                <td>
                    <a class="cell-title" href="{{ route('admin.officers.show', $o) }}">{{ $o->display_name }}</a>
                    <div class="cell-sub">{{ $o->personnelRecord?->rank ?? '—' }}
                        @foreach($o->roles as $r)
                            @if($r->role && $r->role->name !== 'officer') · <x-admin.badge value="" tone="brand">{{ $r->role->label }}</x-admin.badge>@endif
                        @endforeach
                    </div>
                </td>
                <td class="mono">{{ $o->service_number }}</td>
                <td>{{ $o->personnelRecord?->command ?? '—' }}<div class="cell-sub">{{ $o->personnelRecord?->directorate }}</div></td>
                <td><x-admin.badge :value="$o->account_state" /></td>
                <td>{{ number_format($o->devices_count) }}</td>
                <td class="nowrap small">{{ $o->last_seen_at?->diffForHumans() ?? 'Never' }}</td>
                <td class="nowrap small">{{ $o->created_at?->format('d M Y') }}</td>
                <td><div class="row-actions"><a class="btn btn-sm btn-ghost" href="{{ route('admin.officers.show', $o) }}">View</a></div></td>
            </tr>
        @empty
            <tr><td colspan="8"><x-admin.empty icon="users" title="No officers found">
                @if(array_filter($filters)) Try a different search or clear the filters. @else Officers appear here once they register in the app. @endif
            </x-admin.empty></td></tr>
        @endforelse
        </tbody>
    </table></div>
    {{ $officers->links() }}
</div>
@endsection
