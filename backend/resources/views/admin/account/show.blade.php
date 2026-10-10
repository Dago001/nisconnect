@extends('admin.layout')
@section('title', 'My account')
@section('content')
<x-admin.page-header title="My account" subtitle="Your administrator profile and sign-in security.">
    <a class="btn btn-ghost" href="{{ route('admin.account.password') }}"><x-admin.icon name="lock" /> Change password</a>
    <a class="btn" href="{{ route('admin.account.two-factor') }}"><x-admin.icon name="key" /> Two-factor authentication</a>
</x-admin.page-header>
<div class="grid grid-sidebar">
    <div class="card">
        <div class="card-head"><h2>Recent activity</h2></div>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>When</th><th>Action</th><th>Result</th></tr></thead>
            <tbody>
            @forelse($activity as $log)
                <tr><td class="nowrap">{{ $log->created_at?->format('d M Y, H:i') }}</td><td class="mono">{{ $log->action }}</td><td><x-admin.badge :value="$log->result" /></td></tr>
            @empty
                <tr><td colspan="3"><x-admin.empty title="No activity yet" /></td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div>
    <div class="stack">
        <div class="card card-body">
            <h2>{{ $user->display_name }}</h2>
            <dl class="dl">
                <dt>Service No.</dt><dd class="mono">{{ $user->service_number }}</dd>
                <dt>Rank</dt><dd>{{ $user->personnelRecord?->rank ?? '—' }}</dd>
                <dt>Roles</dt><dd>@foreach($user->roles as $r)<x-admin.badge value="" tone="brand">{{ $r->role?->label }}</x-admin.badge> @endforeach</dd>
                <dt>Two-factor</dt><dd>@if($user->hasTwoFactorEnabled())<x-admin.badge value="enabled" />@else<x-admin.badge value="off" tone="warning">Off</x-admin.badge>@endif</dd>
                <dt>Password changed</dt><dd>{{ $user->password_changed_at?->diffForHumans() ?? 'Never' }}</dd>
                <dt>Last sign-in</dt><dd>{{ $user->last_admin_login_at?->format('d M Y, H:i') ?? '—' }}<div class="small muted">{{ $user->last_admin_login_ip }}</div></dd>
            </dl>
        </div>
    </div>
</div>
@endsection
