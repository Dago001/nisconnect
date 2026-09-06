@extends('admin.layout')
@section('title', 'Sign in')
@section('content')
<div style="max-width:360px; margin:60px auto; background:#fff; border:1px solid var(--border); border-radius:12px; padding:24px;">
    <h1 style="color:var(--dark);">Administration Portal</h1>
    <p style="color:var(--grey); font-size:14px;">Authorised NIS administrators only.</p>
    <form method="POST" action="{{ route('admin.login.submit') }}">
        @csrf
        <label style="font-size:13px;">Service Number</label>
        <input type="text" name="service_number" inputmode="numeric" pattern="[0-9]*" value="{{ old('service_number') }}">
        @error('service_number')<div class="err">{{ $message }}</div>@enderror
        <div style="height:12px;"></div>
        <label style="font-size:13px;">Password</label>
        <input type="password" name="password">
        <div style="height:16px;"></div>
        <button class="btn" type="submit" style="width:100%;">Sign in</button>
    </form>
</div>
@endsection
