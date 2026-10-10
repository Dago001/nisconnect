@extends('admin.layout')
@section('title', $user->display_name)
@section('inline-errors', '1')
@section('content')
@php
    $me = auth()->user();
    $p = $user->personnelRecord;
    $canRevokeDevices = $me->hasPermission('devices.revoke') && $canAct;
@endphp
<x-admin.page-header :title="$user->display_name" :subtitle="trim(($p?->rank ?? '').' · Service No. '.$user->service_number, ' ·')" :crumbs="['Officers' => route('admin.officers.index')]">
    <x-admin.badge :value="$user->account_state" />
    @if($lockedOut)<x-admin.badge value="locked" tone="warning">Locked out (too many sign-in attempts)</x-admin.badge>@endif
</x-admin.page-header>

<div class="stats">
    <x-admin.stat label="Messages sent" :value="$counts['messages']" icon="message" />
    <x-admin.stat label="Groups" :value="$counts['groups']" icon="group" />
    <x-admin.stat label="Active devices" :value="$counts['devices']" icon="phone" />
    <x-admin.stat label="Last seen" :value="$user->last_seen_at?->diffForHumans() ?? 'Never'" icon="activity" />
</div>

<div class="grid grid-sidebar">
    <div class="stack">
        <div class="card">
            <div class="card-head"><h2>Personnel record</h2>@if($p)<x-admin.badge :value="$p->status" />@endif</div>
            <div class="card-body">
                @if($p)
                <dl class="dl">
                    <dt>Full name</dt><dd>{{ trim($p->surname.', '.$p->first_name.' '.$p->other_name, ', ') }}</dd>
                    <dt>Service No.</dt><dd class="mono">{{ $p->service_number }}</dd>
                    <dt>Rank</dt><dd>{{ $p->rank ?? '—' }}</dd>
                    <dt>Directorate</dt><dd>{{ $p->directorate ?? '—' }}</dd>
                    <dt>Department</dt><dd>{{ $p->department ?? '—' }}</dd>
                    <dt>Zone</dt><dd>{{ $p->zone ?? '—' }}</dd>
                    <dt>Command</dt><dd>{{ $p->command ?? '—' }}</dd>
                    <dt>Formation</dt><dd>{{ $p->formation ?? '—' }}</dd>
                    <dt>Unit</dt><dd>{{ $p->unit ?? '—' }}</dd>
                    <dt>Posting</dt><dd>{{ $p->posting ?? '—' }}</dd>
                    <dt>Official email</dt><dd>{{ $p->official_email ?? '—' }}</dd>
                    <dt>Source</dt><dd>{{ ucfirst($p->source) }}@if($p->source_synced_at) <span class="muted small">· synced {{ $p->source_synced_at->diffForHumans() }}</span>@endif</dd>
                </dl>
                @else
                    <x-admin.empty icon="id" title="No personnel record linked" />
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-head"><h2>Devices</h2><span class="muted small">{{ $user->devices->count() }} registered</span></div>
            <div class="table-wrap"><table class="table">
                <thead><tr><th>Device</th><th>Platform</th><th>Last active</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @forelse($user->devices as $d)
                    <tr>
                        <td><div class="cell-title">{{ $d->name }}</div><div class="cell-sub">{{ $d->model ?? 'Unknown model' }}@if($d->os_version) · {{ $d->os_version }}@endif @if($d->app_version) · app {{ $d->app_version }}@endif</div></td>
                        <td>{{ ucfirst($d->platform) }}</td>
                        <td class="nowrap small">{{ $d->last_active_at?->diffForHumans() ?? '—' }}</td>
                        <td><x-admin.badge :value="$d->status" /></td>
                        <td><div class="row-actions">
                            @if($canRevokeDevices && $d->status === 'active')
                                <form method="POST" action="{{ route('admin.devices.revoke', $d) }}" data-confirm="Revoke this device?" data-confirm-body="{{ $d->name }} will be signed out and must sign in again." data-confirm-ok="Revoke">
                                    @csrf <button class="btn btn-sm btn-danger" type="submit">Revoke</button>
                                </form>
                            @endif
                        </div></td>
                    </tr>
                @empty
                    <tr><td colspan="5"><x-admin.empty icon="phone" title="No devices registered" /></td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>

        <div class="card">
            <div class="card-head"><h2>Administrative history</h2></div>
            <div class="table-wrap"><table class="table">
                <thead><tr><th>When</th><th>Action</th><th>By</th><th>Result</th></tr></thead>
                <tbody>
                @forelse($audit as $log)
                    <tr>
                        <td class="nowrap small">{{ $log->created_at?->format('d M Y, H:i') }}</td>
                        <td class="mono">{{ $log->action }}@if(! empty($log->metadata['reason']))<div class="cell-sub">Reason: {{ $log->metadata['reason'] }}</div>@endif</td>
                        <td class="small">{{ $actors[$log->actor_id] ?? 'System' }}</td>
                        <td><x-admin.badge :value="$log->result" /></td>
                    </tr>
                @empty
                    <tr><td colspan="4"><x-admin.empty icon="list" title="No administrative actions yet" /></td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>

        <div class="card">
            <div class="card-head"><h2>Security events</h2></div>
            <div class="table-wrap"><table class="table">
                <thead><tr><th>When</th><th>Event</th><th>Severity</th><th>IP</th></tr></thead>
                <tbody>
                @forelse($security as $e)
                    <tr>
                        <td class="nowrap small">{{ $e->created_at?->format('d M Y, H:i') }}</td>
                        <td class="mono">{{ $e->event }}</td>
                        <td><x-admin.badge :value="$e->severity" /></td>
                        <td class="mono small">{{ $e->ip ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4"><x-admin.empty icon="shield" title="No security events" /></td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
    </div>

    <div class="stack">
        <div class="card card-body">
            <h2>Account</h2>
            <dl class="dl">
                <dt>State</dt><dd><x-admin.badge :value="$user->account_state" /></dd>
                <dt>Roles</dt><dd>@forelse($user->roles as $r)<x-admin.badge value="" :tone="$r->role?->name === 'officer' ? '' : 'brand'">{{ $r->role?->label }}</x-admin.badge> @empty — @endforelse</dd>
                <dt>Phone verified</dt><dd>{{ $user->phone_verified_at ? 'Yes' : 'No' }}</dd>
                <dt>Joined</dt><dd>{{ $user->created_at?->format('d M Y') }}</dd>
                <dt>Last seen</dt><dd>{{ $user->last_seen_at?->format('d M Y, H:i') ?? 'Never' }}</dd>
            </dl>
        </div>

        @if($me->hasPermission('officers.manage'))
        <div class="card">
            <div class="card-head"><h2>Actions</h2></div>
            <div class="card-body">
                @if(! $canAct)
                    <p class="muted">Only a super administrator can change this account.</p>
                @else
                    <form method="POST" action="{{ route('admin.officers.update', $user) }}">
                        @csrf @method('PATCH')
                        <div class="field">
                            <label for="display_name">Display name</label>
                            <input type="text" id="display_name" name="display_name" value="{{ old('display_name', $user->display_name) }}" required maxlength="80">
                            @error('display_name')<div class="error">{{ $message }}</div>@enderror
                        </div>
                        <button class="btn btn-ghost" type="submit"><x-admin.icon name="edit" /> Save name</button>
                    </form>
                    <hr style="border:0;border-top:1px solid var(--border);margin:16px 0">
                    <div class="actions" style="flex-direction:column;align-items:stretch">
                        @if($lockedOut || $user->account_state === 'locked')
                            <form method="POST" action="{{ route('admin.officers.unlock', $user) }}">
                                @csrf <button class="btn btn-ghost" type="submit" style="width:100%"><x-admin.icon name="unlock" /> Unlock sign-in</button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('admin.officers.sign-out', $user) }}" data-confirm="Sign this officer out everywhere?" data-confirm-body="Every device will be signed out and marked as revoked. The officer can sign in again." data-confirm-ok="Sign out">
                            @csrf <button class="btn btn-ghost" type="submit" style="width:100%"><x-admin.icon name="logout" /> Sign out everywhere</button>
                        </form>
                        @unless($isSelf)
                            @if($user->account_state !== 'active')
                                <form method="POST" action="{{ route('admin.officers.reactivate', $user) }}" data-confirm="Reactivate this account?" data-confirm-ok="Reactivate">
                                    @csrf <button class="btn" type="submit" style="width:100%"><x-admin.icon name="check" /> Reactivate</button>
                                </form>
                            @endif
                            @if($user->account_state !== 'suspended')
                                <form method="POST" action="{{ route('admin.officers.suspend', $user) }}" data-confirm="Suspend this officer?" data-confirm-body="They will be signed out of every device and cannot use NISconnect until reactivated." data-confirm-ok="Suspend" data-confirm-reason>
                                    @csrf <button class="btn btn-warning" type="submit" style="width:100%"><x-admin.icon name="lock" /> Suspend</button>
                                </form>
                            @endif
                            @if($user->account_state !== 'disabled')
                                <form method="POST" action="{{ route('admin.officers.disable', $user) }}" data-confirm="Disable this account?" data-confirm-body="A disabled account cannot sign in at all. Use this for officers who have left the Service." data-confirm-ok="Disable" data-confirm-reason>
                                    @csrf <button class="btn btn-danger" type="submit" style="width:100%"><x-admin.icon name="x" /> Disable</button>
                                </form>
                            @endif
                        @else
                            <p class="muted small">You cannot suspend or disable your own account.</p>
                        @endunless
                    </div>
                    @error('reason')<div class="field"><div class="error">{{ $message }}</div></div>@enderror
                @endif
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
