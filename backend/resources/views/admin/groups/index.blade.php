@extends('admin.layout')
@section('title', 'Groups')
@section('inline-errors', '1')
@section('content')
<x-admin.page-header title="Groups" subtitle="Every group chat on NISconnect. Create organisational groups for official teams and manage their members." />

<div class="grid grid-sidebar">
    <div class="card">
        <form method="GET" action="{{ route('admin.groups.index') }}" class="filters" role="search">
            <div class="f" style="max-width:320px"><label for="q">Search</label>
                <input id="q" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Group name or description"></div>
            <div class="f"><label for="type">Type</label>
                <select id="type" name="type" data-autosubmit>
                    <option value="">All groups</option>
                    @foreach($types as $k => $v)<option value="{{ $k }}" @selected(($filters['type'] ?? '') === $k)>{{ $v }}</option>@endforeach
                </select></div>
            <div><button class="btn btn-ghost" type="submit"><x-admin.icon name="search" /> Filter</button>
                @if(array_filter($filters))<a class="btn btn-ghost" href="{{ route('admin.groups.index') }}">Clear</a>@endif</div>
        </form>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Group</th><th>Type</th><th>Members</th><th>Created</th><th></th></tr></thead>
            <tbody>
            @forelse($groups as $g)
                <tr>
                    <td><a class="cell-title" href="{{ route('admin.groups.show', $g) }}">{{ $g->name }}</a>
                        <div class="cell-sub">{{ \Illuminate\Support\Str::limit($g->description, 80) ?: '—' }}</div></td>
                    <td><x-admin.badge :value="$g->type" :tone="$g->type === 'organisational' ? 'brand' : ''">{{ $types[$g->type] ?? ucfirst($g->type) }}</x-admin.badge></td>
                    <td>{{ number_format($g->members_count) }}</td>
                    <td class="nowrap"><div>{{ $g->created_at?->format('d M Y') }}</div><div class="cell-sub">{{ $creators[$g->created_by] ?? '—' }}</div></td>
                    <td class="row-actions"><a class="btn btn-sm btn-ghost" href="{{ route('admin.groups.show', $g) }}"><x-admin.icon name="eye" /> Manage</a></td>
                </tr>
            @empty
                <tr><td colspan="5"><x-admin.empty icon="group" title="No groups found">
                    @if(array_filter($filters)) Try a different search. @else Create the first organisational group with the form. @endif
                </x-admin.empty></td></tr>
            @endforelse
            </tbody>
        </table></div>
        {{ $groups->links() }}
    </div>

    <form method="POST" action="{{ route('admin.groups.store') }}" class="card card-body">
        @csrf
        <h2 style="margin-top:0">New organisational group</h2>
        <p class="small muted">An official group for a team, unit or posting. You can add members after it's created.</p>
        <div class="field"><label for="name">Name</label>
            <input id="name" type="text" name="name" value="{{ old('name') }}" required maxlength="120">
            @error('name')<div class="error">{{ $message }}</div>@enderror</div>
        <div class="field"><label for="description">Description <span class="muted small">(optional)</span></label>
            <textarea id="description" name="description" rows="3" maxlength="1000">{{ old('description') }}</textarea>
            @error('description')<div class="error">{{ $message }}</div>@enderror</div>
        <button class="btn" type="submit"><x-admin.icon name="plus" /> Create group</button>
    </form>
</div>
@endsection
