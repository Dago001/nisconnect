@extends('admin.layout')
@section('title', 'Official channels')
@section('inline-errors', '1')
@section('content')
<x-admin.page-header title="Official channels" subtitle="Broadcast channels: publishers post, every subscriber reads." />

<div class="grid grid-sidebar">
    <div class="card">
        <form method="GET" action="{{ route('admin.channels.index') }}" class="filters" role="search">
            <div class="f" style="max-width:320px"><label for="q">Search</label>
                <input id="q" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Channel name or description"></div>
            <div><button class="btn btn-ghost" type="submit"><x-admin.icon name="search" /> Search</button>
                @if(array_filter($filters))<a class="btn btn-ghost" href="{{ route('admin.channels.index') }}">Clear</a>@endif</div>
        </form>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Channel</th><th>Publishers</th><th>Members</th><th>Created</th><th></th></tr></thead>
            <tbody>
            @forelse($channels as $c)
                <tr>
                    <td><a class="cell-title" href="{{ route('admin.channels.show', $c) }}">{{ $c->name }}</a>
                        <div class="cell-sub">{{ \Illuminate\Support\Str::limit($c->description, 80) ?: '—' }}</div></td>
                    <td>{{ number_format($c->publishers_count) }}</td>
                    <td>{{ number_format($c->members_count) }}</td>
                    <td class="nowrap">{{ $c->created_at?->format('d M Y') }}</td>
                    <td class="row-actions"><a class="btn btn-sm btn-ghost" href="{{ route('admin.channels.show', $c) }}"><x-admin.icon name="eye" /> Manage</a></td>
                </tr>
            @empty
                <tr><td colspan="5"><x-admin.empty icon="megaphone" title="No channels found">
                    @if(array_filter($filters)) Try a different search. @else Create the first official channel with the form. @endif
                </x-admin.empty></td></tr>
            @endforelse
            </tbody>
        </table></div>
        {{ $channels->links() }}
    </div>

    <form method="POST" action="{{ route('admin.channels.store') }}" class="card card-body">
        @csrf
        <h2 style="margin-top:0">New channel</h2>
        <p class="small muted">You'll be its first publisher. Officers can subscribe from the app.</p>
        <div class="field"><label for="name">Name</label>
            <input id="name" type="text" name="name" value="{{ old('name') }}" required maxlength="120">
            @error('name')<div class="error">{{ $message }}</div>@enderror</div>
        <div class="field"><label for="description">Description <span class="muted small">(optional)</span></label>
            <textarea id="description" name="description" rows="3" maxlength="1000">{{ old('description') }}</textarea>
            @error('description')<div class="error">{{ $message }}</div>@enderror</div>
        <button class="btn" type="submit"><x-admin.icon name="plus" /> Create channel</button>
    </form>
</div>
@endsection
