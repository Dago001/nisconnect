@extends('admin.layout')
@section('title', 'Dashboard')
@section('content')
<h1>Dashboard</h1>
<div class="cards">
    @foreach(['personnel'=>'Personnel records','accounts'=>'Accounts','active'=>'Active','suspended'=>'Suspended','online'=>'Online now','devices'=>'Active devices','groups'=>'Groups','channels'=>'Channels','messages'=>'Messages','calls'=>'Calls'] as $k=>$label)
    <div class="card"><div class="n">{{ $stats[$k] }}</div><div class="l">{{ $label }}</div></div>
    @endforeach
</div>

<h2 style="margin-top:28px;">Recent security events</h2>
<table>
    <tr><th>Event</th><th>Severity</th><th>When</th></tr>
    @forelse($recentSecurity as $e)
    <tr><td>{{ $e->event }}</td><td>{{ $e->severity }}</td><td>{{ $e->created_at }}</td></tr>
    @empty
    <tr><td colspan="3" style="color:var(--grey);">No security events.</td></tr>
    @endforelse
</table>

<h2 style="margin-top:28px;">Recent audit log</h2>
<table>
    <tr><th>Action</th><th>Resource</th><th>Result</th><th>When</th></tr>
    @forelse($recentAudit as $a)
    <tr><td>{{ $a->action }}</td><td>{{ $a->resource_type }}</td><td>{{ $a->result }}</td><td>{{ $a->created_at }}</td></tr>
    @empty
    <tr><td colspan="4" style="color:var(--grey);">No audit records.</td></tr>
    @endforelse
</table>
@endsection
