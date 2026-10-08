@extends('admin.layout')
@section('title', $group->name)
@section('inline-errors', '1')
@section('content')
<x-admin.page-header :title="$group->name" :subtitle="($types[$group->type] ?? ucfirst($group->type)).' group · '.number_format($members->total()).' '.\Illuminate\Support\Str::plural('member', $members->total())"
    :crumbs="['Groups' => route('admin.groups.index')]">
    <form method="POST" action="{{ route('admin.groups.destroy', $group) }}" data-confirm="Delete “{{ $group->name }}”?"
        data-confirm-body="The group disappears for all its members. Its message history is kept on record." data-confirm-ok="Delete group">
        @csrf @method('DELETE')
        <button class="btn btn-danger" type="submit"><x-admin.icon name="trash" /> Delete group</button>
    </form>
</x-admin.page-header>

<div class="grid grid-sidebar">
    <div class="card">
        <div class="card-head"><h2>Members</h2></div>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Officer</th><th>Service No.</th><th>Role in group</th><th>Joined</th><th></th></tr></thead>
            <tbody>
            @forelse($members as $m)
                <tr>
                    <td><div class="cell-title">{{ $m->user?->display_name ?? 'Unknown officer' }}</div>
                        <div class="cell-sub">{{ $m->user?->personnelRecord?->rank ?? '' }}</div></td>
                    <td class="mono">{{ $m->user?->service_number }}</td>
                    <td><x-admin.badge :value="$m->role" :tone="in_array($m->role, ['owner', 'admin']) ? 'brand' : ''">{{ $roles[$m->role] ?? ucfirst($m->role) }}</x-admin.badge>
                        @if($m->user && ! $m->user->isActive())<x-admin.badge :value="$m->user->account_state" />@endif</td>
                    <td class="nowrap small">{{ $m->created_at?->format('d M Y') }}</td>
                    <td class="row-actions">
                        @if($m->user)
                        <form method="POST" action="{{ route('admin.groups.members.destroy', [$group, $m->user]) }}"
                            data-confirm="Remove {{ $m->user->display_name }} from this group?" data-confirm-ok="Remove">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-ghost" type="submit"><x-admin.icon name="x" /> Remove</button>
                        </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5"><x-admin.empty icon="users" title="No members yet">Add officers by Service Number.</x-admin.empty></td></tr>
            @endforelse
            </tbody>
        </table></div>
        {{ $members->links() }}
    </div>

    <div class="stack">
        <form method="POST" action="{{ route('admin.groups.members.store', $group) }}" class="card card-body">
            @csrf
            <h2 style="margin-top:0">Add member</h2>
            <div class="field"><label for="service_number">Service Number</label>
                <input id="service_number" type="text" name="service_number" value="{{ old('service_number') }}" inputmode="numeric" pattern="[0-9]{1,20}" required class="mono">
                <div class="help">The officer must have an active NISconnect account. Adding an existing member changes their role.</div>
                @error('service_number')<div class="error">{{ $message }}</div>@enderror</div>
            <div class="field"><label for="role">Role in group</label>
                <select id="role" name="role">
                    @foreach($roles as $k => $v)<option value="{{ $k }}" @selected(old('role', 'member') === $k)>{{ $v }}</option>@endforeach
                </select>
                @error('role')<div class="error">{{ $message }}</div>@enderror</div>
            <button class="btn" type="submit"><x-admin.icon name="plus" /> Add member</button>
        </form>

        <form method="POST" action="{{ route('admin.groups.update', $group) }}" class="card card-body">
            @csrf @method('PUT')
            <h2 style="margin-top:0">Details</h2>
            <div class="field"><label for="name">Name</label>
                <input id="name" type="text" name="name" value="{{ old('name', $group->name) }}" required maxlength="120">
                @error('name')<div class="error">{{ $message }}</div>@enderror</div>
            <div class="field"><label for="description">Description</label>
                <textarea id="description" name="description" rows="3" maxlength="1000">{{ old('description', $group->description) }}</textarea>
                @error('description')<div class="error">{{ $message }}</div>@enderror</div>
            <dl class="dl small" style="margin-bottom:14px">
                <dt>Created</dt><dd>{{ $group->created_at?->format('d M Y') }} @if($creator) by {{ $creator->display_name }} @endif</dd>
            </dl>
            <button class="btn btn-ghost" type="submit"><x-admin.icon name="check" /> Save details</button>
        </form>
    </div>
</div>
@endsection
