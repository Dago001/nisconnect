@extends('admin.layout')
@section('title', $channel->name)
@section('inline-errors', '1')
@section('content')
<x-admin.page-header :title="$channel->name" :subtitle="'Official channel · '.number_format($publishers).' '.\Illuminate\Support\Str::plural('publisher', $publishers).' · '.number_format($members->total()).' '.\Illuminate\Support\Str::plural('member', $members->total())"
    :crumbs="['Official channels' => route('admin.channels.index')]">
    <form method="POST" action="{{ route('admin.channels.destroy', $channel) }}" data-confirm="Delete “{{ $channel->name }}”?"
        data-confirm-body="The channel disappears from the officer app. Its past posts are kept on record." data-confirm-ok="Delete channel">
        @csrf @method('DELETE')
        <button class="btn btn-danger" type="submit"><x-admin.icon name="trash" /> Delete channel</button>
    </form>
</x-admin.page-header>

@if($publishers === 0)
    <div class="alert alert-warning">This channel has no publishers, so nobody can post to it. Add a publisher below.</div>
@endif

<div class="grid grid-sidebar">
    <div class="card">
        <div class="card-head"><h2>Members</h2></div>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Officer</th><th>Service No.</th><th>Role</th><th>Joined</th><th></th></tr></thead>
            <tbody>
            @forelse($members as $m)
                <tr>
                    <td><div class="cell-title">{{ $m->display_name }}</div><div class="cell-sub">{{ $m->rank }}</div></td>
                    <td class="mono">{{ $m->service_number }}</td>
                    <td><x-admin.badge :value="$m->role" :tone="$m->role === 'publisher' ? 'brand' : ''">{{ $roles[$m->role] ?? ucfirst($m->role) }}</x-admin.badge>
                        @if($m->account_state !== 'active')<x-admin.badge :value="$m->account_state" />@endif</td>
                    <td class="nowrap small">{{ $m->created_at?->format('d M Y') }}</td>
                    <td class="row-actions">
                        <form method="POST" action="{{ route('admin.channels.members.destroy', [$channel, $m->user_id]) }}"
                            data-confirm="Remove {{ $m->display_name }} from this channel?" data-confirm-ok="Remove">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-ghost" type="submit"><x-admin.icon name="x" /> Remove</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5"><x-admin.empty icon="users" title="No members yet">Add publishers and subscribers by Service Number.</x-admin.empty></td></tr>
            @endforelse
            </tbody>
        </table></div>
        {{ $members->links() }}
    </div>

    <div class="stack">
        <form method="POST" action="{{ route('admin.channels.members.store', $channel) }}" class="card card-body">
            @csrf
            <h2 style="margin-top:0">Add member</h2>
            <div class="field"><label for="service_number">Service Number</label>
                <input id="service_number" type="text" name="service_number" value="{{ old('service_number') }}" inputmode="numeric" pattern="[0-9]{1,20}" required class="mono">
                <div class="help">The officer must have an active account. Adding an existing member changes their role.</div>
                @error('service_number')<div class="error">{{ $message }}</div>@enderror</div>
            <div class="field"><label for="role">Role</label>
                <select id="role" name="role">
                    @foreach($roles as $k => $v)<option value="{{ $k }}" @selected(old('role', 'subscriber') === $k)>{{ $v }}</option>@endforeach
                </select>
                <div class="help">Publishers can post to the channel. Subscribers can only read.</div>
                @error('role')<div class="error">{{ $message }}</div>@enderror</div>
            <button class="btn" type="submit"><x-admin.icon name="plus" /> Add member</button>
        </form>

        <form method="POST" action="{{ route('admin.channels.update', $channel) }}" class="card card-body">
            @csrf @method('PUT')
            <h2 style="margin-top:0">Details</h2>
            <div class="field"><label for="name">Name</label>
                <input id="name" type="text" name="name" value="{{ old('name', $channel->name) }}" required maxlength="120">
                @error('name')<div class="error">{{ $message }}</div>@enderror</div>
            <div class="field"><label for="description">Description</label>
                <textarea id="description" name="description" rows="3" maxlength="1000">{{ old('description', $channel->description) }}</textarea>
                @error('description')<div class="error">{{ $message }}</div>@enderror</div>
            <dl class="dl small" style="margin-bottom:14px">
                <dt>Created</dt><dd>{{ $channel->created_at?->format('d M Y') }} @if($creator) by {{ $creator->display_name }} @endif</dd>
            </dl>
            <button class="btn btn-ghost" type="submit"><x-admin.icon name="check" /> Save details</button>
        </form>
    </div>
</div>
@endsection
