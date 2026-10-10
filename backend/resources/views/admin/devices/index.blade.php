@extends('admin.layout')
@section('title', 'Devices')
@section('content')
@php $me = auth()->user(); $canRevoke = $me->hasPermission('devices.revoke'); @endphp
<x-admin.page-header title="Devices" subtitle="Phones, tablets and browsers signed in to NISconnect. Revoking a device signs it out immediately." />

<div class="stats">
    <x-admin.stat label="Active devices" :value="$counts['active']" icon="phone" />
    <x-admin.stat label="Revoked devices" :value="$counts['revoked']" icon="lock" />
</div>

<div class="card">
    <form method="GET" action="{{ route('admin.devices.index') }}" class="filters" role="search">
        <div class="f" style="max-width:320px">
            <label for="q">Officer</label>
            <input type="search" id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Name or Service Number">
        </div>
        <div class="f">
            <label for="platform">Platform</label>
            <select id="platform" name="platform" data-autosubmit>
                <option value="">All platforms</option>
                @foreach($platforms as $p)<option value="{{ $p }}" @selected(($filters['platform'] ?? '') === $p)>{{ $p === 'ios' ? 'iOS' : ucfirst($p) }}</option>@endforeach
            </select>
        </div>
        <div class="f">
            <label for="status">Status</label>
            <select id="status" name="status" data-autosubmit>
                <option value="">Any status</option>
                @foreach($statuses as $s)<option value="{{ $s }}" @selected(($filters['status'] ?? '') === $s)>{{ ucfirst($s) }}</option>@endforeach
            </select>
        </div>
        <div class="actions">
            <button class="btn" type="submit"><x-admin.icon name="search" /> Search</button>
            @if(array_filter($filters))<a class="btn btn-ghost" href="{{ route('admin.devices.index') }}">Clear</a>@endif
        </div>
    </form>

    <div class="table-wrap"><table class="table">
        <thead><tr><th>Device</th><th>Officer</th><th>Platform</th><th>App version</th><th>Last active</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @forelse($devices as $d)
            <tr>
                <td><div class="cell-title">{{ $d->name }}</div><div class="cell-sub">{{ $d->model ?? 'Unknown model' }}@if($d->os_version) · {{ $d->os_version }}@endif</div></td>
                <td>
                    @if($d->user)
                        @if($me->hasPermission('officers.view'))<a href="{{ route('admin.officers.show', $d->user) }}">{{ $d->user->display_name }}</a>@else{{ $d->user->display_name }}@endif
                        <div class="cell-sub"><span class="mono">{{ $d->user->service_number }}</span>@if($d->user->personnelRecord?->rank) · {{ $d->user->personnelRecord->rank }}@endif</div>
                    @else <span class="muted">—</span> @endif
                </td>
                <td>{{ $d->platform === 'ios' ? 'iOS' : ucfirst($d->platform) }}</td>
                <td class="mono">{{ $d->app_version ?? '—' }}</td>
                <td class="nowrap small">{{ $d->last_active_at?->diffForHumans() ?? '—' }}</td>
                <td><x-admin.badge :value="$d->status" /></td>
                <td><div class="row-actions">
                    @if($canRevoke && $d->status === 'active')
                        <form method="POST" action="{{ route('admin.devices.revoke', $d) }}" data-confirm="Revoke this device?" data-confirm-body="{{ $d->name }} will be signed out and must sign in again." data-confirm-ok="Revoke">
                            @csrf <button class="btn btn-sm btn-danger" type="submit">Revoke</button>
                        </form>
                    @endif
                </div></td>
            </tr>
        @empty
            <tr><td colspan="7"><x-admin.empty icon="phone" title="No devices found">
                @if(array_filter($filters)) Try a different search or clear the filters. @else Devices appear here when officers sign in to the app. @endif
            </x-admin.empty></td></tr>
        @endforelse
        </tbody>
    </table></div>
    {{ $devices->links() }}
</div>
@endsection
