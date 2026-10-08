@extends('admin.layout')
@php $editing = $record->exists; @endphp
@section('title', $editing ? 'Edit personnel record' : 'Add personnel record')
@section('inline-errors', '1')
@section('content')
<x-admin.page-header :title="$editing ? 'Edit personnel record' : 'Add personnel record'"
    :subtitle="$editing ? $record->surname.', '.$record->first_name.' · '.$record->service_number : 'Add an officer to the authorised personnel list so they can register.'"
    :crumbs="['Personnel records' => route('admin.personnel.index')]" />

@include('admin.personnel.provider-notice')

@if($errors->any())
    <div class="alert alert-danger">Please fix the highlighted fields below.</div>
@endif

<div class="grid grid-sidebar">
    <form method="POST" action="{{ $editing ? route('admin.personnel.update', $record) : route('admin.personnel.store') }}" class="card card-body">
        @csrf
        @if($editing) @method('PUT') @endif
        @php
            $locked = $editing && $account;
            $field = fn ($name) => old($name, $record->{$name});
        @endphp

        <h2 style="margin-top:0">Identity</h2>
        <div class="form-row">
            <div class="field"><label for="service_number">Service Number</label>
                <input id="service_number" type="text" name="service_number" value="{{ $field('service_number') }}"
                    inputmode="numeric" pattern="[0-9]{1,20}" maxlength="20" required @if($locked) readonly @endif class="mono">
                <div class="help">@if($locked) Locked — an account is registered with this Service Number. @else Digits only. Leading zeroes are kept (001234 is not 1234). @endif</div>
                @error('service_number')<div class="error">{{ $message }}</div>@enderror</div>
            <div class="field"><label for="status">Status</label>
                <select id="status" name="status" required>
                    @foreach($statuses as $s)<option value="{{ $s }}" @selected($field('status') === $s)>{{ ucfirst($s) }}</option>@endforeach
                </select>
                <div class="help">Only active officers can register or sign in. Changing an active officer to another status suspends their account.</div>
                @error('status')<div class="error">{{ $message }}</div>@enderror</div>
        </div>
        <div class="form-row">
            @foreach(['surname' => 'Surname', 'first_name' => 'First name', 'other_name' => 'Other name'] as $name => $label)
                <div class="field"><label for="{{ $name }}">{{ $label }}@if($name === 'other_name') <span class="muted small">(optional)</span>@endif</label>
                    <input id="{{ $name }}" type="text" name="{{ $name }}" value="{{ $field($name) }}" maxlength="255" @if($name !== 'other_name') required @endif>
                    @error($name)<div class="error">{{ $message }}</div>@enderror</div>
            @endforeach
        </div>
        <div class="form-row">
            <div class="field"><label for="rank">Rank</label>
                <input id="rank" type="text" name="rank" value="{{ $field('rank') }}" maxlength="255">
                @error('rank')<div class="error">{{ $message }}</div>@enderror</div>
            <div class="field"><label for="official_email">Official email</label>
                <input id="official_email" type="email" name="official_email" value="{{ $field('official_email') }}" maxlength="255">
                @error('official_email')<div class="error">{{ $message }}</div>@enderror</div>
        </div>

        <h2>Posting</h2>
        <div class="form-row">
            @foreach(['directorate' => 'Directorate', 'department' => 'Department', 'zone' => 'Zone', 'command' => 'Command', 'formation' => 'Formation', 'unit' => 'Unit'] as $name => $label)
                <div class="field"><label for="{{ $name }}">{{ $label }}</label>
                    <input id="{{ $name }}" type="text" name="{{ $name }}" value="{{ $field($name) }}" maxlength="255">
                    @error($name)<div class="error">{{ $message }}</div>@enderror</div>
            @endforeach
        </div>
        <div class="field"><label for="posting">Posting description</label>
            <input id="posting" type="text" name="posting" value="{{ $field('posting') }}" maxlength="255" placeholder="e.g. Service Headquarters, Abuja">
            @error('posting')<div class="error">{{ $message }}</div>@enderror</div>

        <div style="display:flex;gap:8px">
            <button class="btn" type="submit"><x-admin.icon name="check" /> {{ $editing ? 'Save changes' : 'Add record' }}</button>
            <a class="btn btn-ghost" href="{{ route('admin.personnel.index') }}">Cancel</a>
        </div>
    </form>

    <div class="stack">
        <div class="card card-body">
            <h2 style="margin-top:0">Account</h2>
            @if($account)
                <dl class="dl">
                    <dt>Name</dt><dd>{{ $account->display_name }}</dd>
                    <dt>State</dt><dd><x-admin.badge :value="$account->account_state" /></dd>
                    <dt>Registered</dt><dd>{{ $account->created_at?->format('d M Y') }}</dd>
                </dl>
                @if(auth()->user()->hasPermission('officers.view'))
                    <p><a href="{{ route('admin.officers.show', $account) }}">Open officer account</a></p>
                @endif
            @else
                <p class="muted">No NISconnect account is registered for this Service Number yet.</p>
            @endif
        </div>
        @if($editing)
            <div class="card card-body">
                <h2 style="margin-top:0">Record</h2>
                <dl class="dl">
                    <dt>Source</dt><dd>{{ $record->source === 'admin' ? 'Added by an administrator' : ucfirst((string) $record->source) }}</dd>
                    <dt>Last synced</dt><dd>{{ $record->source_synced_at?->format('d M Y, H:i') ?? '—' }}</dd>
                    <dt>Updated</dt><dd>{{ $record->updated_at?->format('d M Y, H:i') }}</dd>
                </dl>
            </div>
        @endif
    </div>
</div>
@endsection
