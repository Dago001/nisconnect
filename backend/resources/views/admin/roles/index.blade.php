@extends('admin.layout')
@section('title', 'Roles & permissions')
@section('content')
<x-admin.page-header title="Roles & permissions" subtitle="Choose what each role can do. Changes apply straight away to everyone holding the role." :crumbs="['Administrators' => route('admin.administrators.index')]" />

<div class="alert alert-info">Tick the boxes in a role's column, then press <strong>Save</strong> at the top of that column. Super Administrators always hold every permission, so their column is locked.</div>

<div class="card">
    <div class="table-wrap"><table class="table">
        <thead>
            <tr>
                <th style="min-width:240px">Permission</th>
                @foreach($roles as $role)
                    @php $locked = $role->name === 'super_admin'; @endphp
                    <th style="text-align:center;min-width:130px;vertical-align:top;text-transform:none;letter-spacing:0">
                        <div style="font-size:12.5px;color:var(--text)">{{ $role->label }}</div>
                        <div class="small muted" style="font-weight:400">{{ number_format($holders[$role->id] ?? 0) }} {{ \Illuminate\Support\Str::plural('holder', (int) ($holders[$role->id] ?? 0)) }}</div>
                        @if($locked)
                            <x-admin.badge value="" tone="danger" style="margin-top:6px"><x-admin.icon name="lock" style="width:12px;height:12px" /> Locked</x-admin.badge>
                        @else
                            <form id="role-{{ $role->id }}" method="POST" action="{{ route('admin.roles.update', $role) }}" style="margin-top:6px">
                                @csrf @method('PUT')
                                <button class="btn btn-sm" type="submit" aria-label="Save permissions for {{ $role->label }}">Save</button>
                            </form>
                            <label class="check small" style="justify-content:center;margin-top:6px;font-weight:400"><input type="checkbox" data-check-all="role-{{ $role->id }}" aria-label="Select all for {{ $role->label }}"> All</label>
                        @endif
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody>
        @foreach($groups as $group => $permissions)
            <tr><td colspan="{{ $roles->count() + 1 }}" style="background:var(--surface-2);font-weight:700;font-size:12px;text-transform:uppercase;letter-spacing:.05em;color:var(--muted)">{{ $group }}</td></tr>
            @foreach($permissions as $name => $label)
                <tr>
                    <td><div>{{ $label }}</div><div class="cell-sub mono">{{ $name }}</div></td>
                    @foreach($roles as $role)
                        @php $locked = $role->name === 'super_admin'; $on = in_array($name, $granted[$role->id], true); @endphp
                        <td style="text-align:center">
                            @if($locked)
                                <input type="checkbox" checked disabled aria-label="{{ $label }} — {{ $role->label }} (always granted)">
                            @else
                                <input type="checkbox" name="permissions[]" value="{{ $name }}" form="role-{{ $role->id }}" data-check-group="role-{{ $role->id }}" @checked($on) aria-label="{{ $label }} — {{ $role->label }}">
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
        @endforeach
        </tbody>
    </table></div>
</div>
@endsection
