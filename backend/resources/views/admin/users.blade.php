@extends('admin.layout')
@section('title', 'Users')
@section('content')
<h1>Users</h1>
<form method="GET" action="{{ route('admin.users') }}" style="margin-bottom:16px; display:flex; gap:8px; max-width:400px;">
    <input type="text" name="q" value="{{ $q }}" placeholder="Search name or Service Number">
    <button class="btn" type="submit">Search</button>
</form>
<table>
    <tr><th>Service No.</th><th>Name</th><th>Rank</th><th>State</th><th>Actions</th></tr>
    @forelse($users as $u)
    <tr>
        <td>{{ $u->service_number }}</td>
        <td>{{ $u->display_name }}</td>
        <td>{{ $u->personnelRecord?->rank }}</td>
        <td><span class="pill {{ $u->account_state }}">{{ $u->account_state }}</span></td>
        <td style="display:flex; gap:6px;">
            @if($u->account_state === 'active')
            <form method="POST" action="{{ route('admin.users.suspend', $u) }}">@csrf<button class="btn warn">Suspend</button></form>
            @else
            <form method="POST" action="{{ route('admin.users.reactivate', $u) }}">@csrf<button class="btn">Reactivate</button></form>
            @endif
            <form method="POST" action="{{ route('admin.users.revoke', $u) }}">@csrf<button class="btn grey">Revoke devices</button></form>
        </td>
    </tr>
    @empty
    <tr><td colspan="5" style="color:var(--grey);">No users found.</td></tr>
    @endforelse
</table>
<div style="margin-top:16px;">{{ $users->links() }}</div>
@endsection
