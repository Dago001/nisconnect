@extends('admin.layout')
@section('title', 'Administrators')
@section('inline-errors', '1')
@section('content')
<x-admin.page-header title="Administrators" subtitle="Officers who can sign in to this portal, and what they can do here.">
    @if($me->hasPermission('roles.manage'))
        <a class="btn btn-ghost" href="{{ route('admin.roles.index') }}"><x-admin.icon name="key" /> Roles &amp; permissions</a>
    @endif
</x-admin.page-header>

@if($temporaryPassword)
    <div class="alert alert-warning" role="alert">
        <strong>Temporary password for {{ $temporaryPassword['name'] }} ({{ $temporaryPassword['service_number'] }}):</strong>
        <div class="mono" style="font-size:16px;margin:6px 0;user-select:all">{{ $temporaryPassword['password'] }}</div>
        <div class="small">This is shown only once. Give it to the officer in person or over a secure channel. They must choose a new password when they first sign in.</div>
    </div>
@endif

<div class="grid grid-sidebar">
    <div class="card">
        <div class="card-head"><h2>Current administrators</h2><span class="muted small">{{ $admins->count() }} {{ \Illuminate\Support\Str::plural('person', $admins->count()) }}</span></div>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Administrator</th><th>Roles</th><th>Two-factor</th><th>Last sign-in</th><th>Password</th><th></th></tr></thead>
            <tbody>
            @forelse($admins as $a)
                @php $protected = $a->isSuperAdmin() && ! $me->isSuperAdmin(); $self = $a->id === $me->id; @endphp
                <tr>
                    <td>
                        <a class="cell-title" href="{{ $me->hasPermission('officers.view') ? route('admin.officers.show', $a) : '#' }}">{{ $a->display_name }}</a>
                        <div class="cell-sub"><span class="mono">{{ $a->service_number }}</span> · {{ $a->personnelRecord?->rank ?? '—' }}@if($self) · <strong>You</strong>@endif</div>
                        @if($a->account_state !== 'active')<x-admin.badge :value="$a->account_state" />@endif
                    </td>
                    <td>
                        @foreach($a->roles as $r)
                            @continue(! in_array($r->role?->name, \App\Support\AdminPermissions::ADMIN_ROLES, true))
                            <div style="display:flex;gap:6px;align-items:center;margin:2px 0">
                                <x-admin.badge value="" :tone="$r->role->name === 'super_admin' ? 'danger' : 'brand'">{{ $r->role->label }}</x-admin.badge>
                                @if($r->scope_id)<span class="small muted">{{ $directorateNames[$r->scope_id] ?? 'Scoped' }}</span>@endif
                                @if(! $self && ! $protected && ! ($r->role->name === 'super_admin' && ! $me->isSuperAdmin()))
                                    <form method="POST" action="{{ route('admin.administrators.revoke', $r) }}" data-confirm="Remove the {{ $r->role->label }} role?" data-confirm-body="{{ $a->display_name }} will lose the access this role gives straight away." data-confirm-ok="Remove role">
                                        @csrf @method('DELETE')
                                        <button class="link-btn small" type="submit" aria-label="Remove {{ $r->role->label }} role from {{ $a->display_name }}">Remove</button>
                                    </form>
                                @endif
                            </div>
                        @endforeach
                    </td>
                    <td>@if($a->hasTwoFactorEnabled())<x-admin.badge value="enabled" />@else<x-admin.badge value="off" tone="warning">Off</x-admin.badge>@endif</td>
                    <td class="small nowrap">{{ $a->last_admin_login_at?->diffForHumans() ?? 'Never' }}</td>
                    <td class="small nowrap">
                        @if(! $a->password_hash) <span class="muted">Not set</span>
                        @else Changed {{ $a->password_changed_at?->diffForHumans() ?? 'unknown' }}@if($a->must_change_password)<div><x-admin.badge value="pending" tone="warning">Temporary</x-admin.badge></div>@endif
                        @endif
                    </td>
                    <td><div class="row-actions">
                        @if(! $self && ! $protected)
                            <form method="POST" action="{{ route('admin.administrators.reset-password', $a) }}" data-confirm="Issue a temporary password?" data-confirm-body="{{ $a->display_name }}'s current password stops working and they are signed out of the portal." data-confirm-ok="Reset password">
                                @csrf <button class="btn btn-sm btn-ghost" type="submit"><x-admin.icon name="lock" /> Reset password</button>
                            </form>
                            @if($a->hasTwoFactorEnabled())
                                <form method="POST" action="{{ route('admin.administrators.reset-two-factor', $a) }}" data-confirm="Reset two-factor authentication?" data-confirm-body="Use this when {{ $a->display_name }} has lost their authenticator. They will set it up again at next sign-in." data-confirm-ok="Reset two-factor">
                                    @csrf <button class="btn btn-sm btn-ghost" type="submit"><x-admin.icon name="key" /> Reset 2FA</button>
                                </form>
                            @endif
                        @endif
                    </div></td>
                </tr>
            @empty
                <tr><td colspan="6"><x-admin.empty icon="shield" title="No administrators yet" /></td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div>

    <div class="card">
        <div class="card-head"><h2>Appoint an administrator</h2></div>
        <form method="POST" action="{{ route('admin.administrators.store') }}" class="card-body">
            @csrf
            <div class="field">
                <label for="service_number">Service Number</label>
                <input type="text" id="service_number" name="service_number" value="{{ old('service_number') }}" inputmode="numeric" pattern="[0-9]+" maxlength="20" required autocomplete="off">
                <div class="help">The officer must already have a NISconnect account.</div>
                @error('service_number')<div class="error">{{ $message }}</div>@enderror
            </div>
            <div class="field">
                <label for="role">Role</label>
                <select id="role" name="role" required>
                    @foreach($roles as $r)<option value="{{ $r->name }}" @selected(old('role', 'nis_admin') === $r->name)>{{ $r->label }}</option>@endforeach
                </select>
                @error('role')<div class="error">{{ $message }}</div>@enderror
            </div>
            <div class="field">
                <label for="scope_id">Directorate <span class="muted">(Directorate Administrators only)</span></label>
                <select id="scope_id" name="scope_id">
                    <option value="">— Not applicable —</option>
                    @foreach($directorates as $d)<option value="{{ $d->id }}" @selected(old('scope_id') === $d->id)>{{ $d->name }} ({{ $d->code }})</option>@endforeach
                </select>
                @error('scope_id')<div class="error">{{ $message }}</div>@enderror
            </div>
            <div class="field">
                <label class="check"><input type="checkbox" name="temporary_password" value="1" @checked(old('temporary_password', '1'))> Issue a temporary password if they don't have one</label>
                <div class="help">The password is shown once. They must change it at first sign-in.</div>
            </div>
            <button class="btn" type="submit"><x-admin.icon name="plus" /> Appoint</button>
            <p class="small muted" style="margin-top:12px">There {{ $superAdminCount === 1 ? 'is 1 active super administrator' : 'are '.$superAdminCount.' active super administrators' }}. The last one can't be removed.</p>
        </form>
    </div>
</div>
@endsection
