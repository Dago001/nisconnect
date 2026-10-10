@extends('admin.layout')
@section('title', 'Change password')
@section('inline-errors', '1')
@section('content')
<x-admin.page-header title="Change password" :crumbs="['My account' => route('admin.account')]" />
<div class="card card-body" style="max-width:520px">
    @php $min = app(\App\Services\Admin\SettingsService::class)->get('admin_password_min_length'); @endphp
    <p class="muted" style="margin-top:0">Use at least {{ $min }} characters with upper and lower case letters, a number and a symbol. Don't reuse a password from another site.</p>
    <form method="POST" action="{{ route('admin.account.password.update') }}">
        @csrf @method('PUT')
        <div class="field"><label for="current_password">Current password</label>
            <input id="current_password" type="password" name="current_password" autocomplete="current-password" required>
            @error('current_password')<div class="error">{{ $message }}</div>@enderror</div>
        <div class="field"><label for="password">New password</label>
            <input id="password" type="password" name="password" autocomplete="new-password" required minlength="{{ $min }}">
            @error('password')<div class="error">{{ $message }}</div>@enderror</div>
        <div class="field"><label for="password_confirmation">Confirm new password</label>
            <input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" required></div>
        <button class="btn" type="submit">Change password</button>
    </form>
</div>
@endsection
