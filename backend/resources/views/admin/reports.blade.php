@extends('admin.layout')
@section('title', 'Reports')
@section('content')
<h1>Reports</h1>
<form method="GET" style="margin-bottom:12px;">
    <select name="status" onchange="this.form.submit()">
        @foreach(['open','reviewing','actioned','dismissed','all'] as $s)
            <option value="{{ $s }}" @selected($status===$s)>{{ ucfirst($s) }}</option>
        @endforeach
    </select>
</form>
<table>
    <tr><th>Target</th><th>Reason</th><th>Status</th><th>When</th><th>Actions</th></tr>
    @forelse($reports as $r)
    <tr>
        <td>{{ $r->target_type }}</td>
        <td>{{ $r->reason }}</td>
        <td><span class="pill">{{ $r->status }}</span></td>
        <td>{{ $r->created_at }}</td>
        <td style="display:flex; gap:6px;">
            <form method="POST" action="{{ route('admin.reports.action', $r) }}">@csrf<input type="hidden" name="status" value="actioned"><button class="btn">Action</button></form>
            <form method="POST" action="{{ route('admin.reports.action', $r) }}">@csrf<input type="hidden" name="status" value="dismissed"><button class="btn grey">Dismiss</button></form>
        </td>
    </tr>
    @empty
    <tr><td colspan="5" style="color:var(--grey);">No reports.</td></tr>
    @endforelse
</table>
<div style="margin-top:16px;">{{ $reports->links() }}</div>
@endsection
