@extends('admin.layout')
@section('title', 'Audit log')
@section('content')
<h1>Audit log</h1>
<table>
    <tr><th>Action</th><th>Actor</th><th>Resource</th><th>Result</th><th>IP</th><th>When</th></tr>
    @forelse($logs as $l)
    <tr>
        <td>{{ $l->action }}</td>
        <td style="font-family:monospace; font-size:12px;">{{ Str::limit($l->actor_id, 8, '') }}</td>
        <td>{{ $l->resource_type }}</td>
        <td>{{ $l->result }}</td>
        <td>{{ $l->ip }}</td>
        <td>{{ $l->created_at }}</td>
    </tr>
    @empty
    <tr><td colspan="6" style="color:var(--grey);">No audit records.</td></tr>
    @endforelse
</table>
<div style="margin-top:16px;">{{ $logs->links() }}</div>
@endsection
