@extends('admin.layout')
@section('title', 'Security events')
@section('content')
<h1>Security events</h1>
<table>
    <tr><th>Event</th><th>Severity</th><th>User</th><th>IP</th><th>When</th></tr>
    @forelse($events as $e)
    <tr>
        <td>{{ $e->event }}</td>
        <td><span class="pill {{ $e->severity==='critical'?'suspended':'active' }}">{{ $e->severity }}</span></td>
        <td style="font-family:monospace; font-size:12px;">{{ Str::limit($e->user_id, 8, '') }}</td>
        <td>{{ $e->ip }}</td>
        <td>{{ $e->created_at }}</td>
    </tr>
    @empty
    <tr><td colspan="5" style="color:var(--grey);">No security events.</td></tr>
    @endforelse
</table>
<div style="margin-top:16px;">{{ $events->links() }}</div>
@endsection
