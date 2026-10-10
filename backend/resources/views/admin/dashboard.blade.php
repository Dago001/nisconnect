@extends('admin.layout')
@section('title', 'Dashboard')
@section('content')
@php $me = auth()->user(); @endphp
<x-admin.page-header title="Dashboard" subtitle="Welcome back, {{ $me->display_name }}. Here's what's happening across NISconnect.">
    @if($me->hasPermission('announcements.send'))
        <a class="btn" href="{{ route('admin.announcements.index') }}"><x-admin.icon name="bell" /> New announcement</a>
    @endif
</x-admin.page-header>

@if(! $me->hasTwoFactorEnabled())
    <div class="alert alert-warning">Your account isn't protected by two-factor authentication. <a href="{{ route('admin.account.two-factor') }}">Set it up now</a>.</div>
@endif

<div class="stats">
    <x-admin.stat label="Registered officers" :value="$stats['accounts']" icon="users" :hint="$coverage.'% of '.number_format($stats['personnel']).' personnel'" :href="$me->hasPermission('officers.view') ? route('admin.officers.index') : null" />
    <x-admin.stat label="Active accounts" :value="$stats['active']" icon="check" :hint="number_format($stats['suspended']).' suspended'" />
    <x-admin.stat label="Online now" :value="$stats['online']" icon="activity" />
    <x-admin.stat label="Active devices" :value="$stats['devices']" icon="phone" :href="$me->hasPermission('devices.view') ? route('admin.devices.index') : null" />
    <x-admin.stat label="Messages today" :value="$stats['messages_today']" icon="message" />
    <x-admin.stat label="Calls today" :value="$stats['calls_today']" icon="call" />
    <x-admin.stat label="Open reports" :value="$stats['open_reports']" icon="flag" :href="$me->hasPermission('reports.review') ? route('admin.reports.index') : null" />
    <x-admin.stat label="Security alerts (24h)" :value="$stats['security_24h']" icon="alert" :href="$me->hasPermission('security.view') ? route('admin.security.index', ['severity' => 'warning']) : null" />
</div>

<div class="grid grid-2" style="margin-bottom:16px">
    <div class="card">
        <div class="card-head"><h2>New registrations</h2><span class="muted small">Last 14 days · {{ number_format(array_sum($charts['signups'])) }}</span></div>
        <div class="card-body"><x-admin.bar-chart :series="$charts['signups']" label="New registrations per day" /></div>
    </div>
    <div class="card">
        <div class="card-head"><h2>Messages sent</h2><span class="muted small">Last 14 days · {{ number_format(array_sum($charts['messages'])) }}</span></div>
        <div class="card-body"><x-admin.bar-chart :series="$charts['messages']" :alt="true" label="Messages per day" /></div>
    </div>
</div>

<div class="grid grid-2">
    @if($me->hasPermission('security.view'))
    <div class="card">
        <div class="card-head"><h2>Recent security events</h2><a class="small" href="{{ route('admin.security.index') }}">View all</a></div>
        <div class="table-wrap"><table class="table">
            <tbody>
            @forelse($recentSecurity as $e)
                <tr><td><x-admin.badge :value="$e->severity" /></td><td class="mono">{{ $e->event }}</td><td class="muted small nowrap">{{ $e->created_at?->diffForHumans() }}</td></tr>
            @empty
                <tr><td><x-admin.empty icon="shield" title="No security events" /></td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div>
    @endif
    @if($me->hasPermission('audit.view'))
    <div class="card">
        <div class="card-head"><h2>Recent administrative activity</h2><a class="small" href="{{ route('admin.audit.index') }}">View all</a></div>
        <div class="table-wrap"><table class="table">
            <tbody>
            @forelse($recentAudit as $log)
                <tr><td class="mono">{{ $log->action }}</td><td class="small">{{ $actors[$log->actor_id] ?? 'System' }}</td><td class="muted small nowrap">{{ $log->created_at?->diffForHumans() }}</td></tr>
            @empty
                <tr><td><x-admin.empty icon="list" title="No activity yet" /></td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div>
    @endif
</div>
@endsection
