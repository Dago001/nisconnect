@extends('admin.layout')
@section('title', 'Sign in')
@section('content')
<div class="auth">
    <div class="auth-hero">
        <div style="display:flex;gap:12px;align-items:center">
            <img src="{{ asset('admin/nis-logo.jpg') }}" alt="" width="48" height="48" style="border-radius:10px;background:#fff">
            <div><strong style="font-size:18px">NISconnect</strong><div style="opacity:.8;font-size:13px">Nigeria Immigration Service</div></div>
        </div>
        <div>
            <h1>Administration portal</h1>
            <p style="opacity:.85;max-width:420px">Restricted to authorised NIS administrators. Every action in this portal is recorded in the audit log.</p>
        </div>
        <div style="opacity:.7;font-size:12px">Unauthorised access is prohibited and may be prosecuted.</div>
    </div>
    <div class="auth-panel">
        <div class="auth-box">
            <h2 style="font-size:22px">Sign in</h2>
            <p class="muted" style="margin-top:0">Use your Service Number and administrator password.</p>
            @if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
            @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
            <form method="POST" action="{{ route('admin.login.submit') }}" novalidate>
                @csrf
                <div class="field">
                    <label for="service_number">Service Number</label>
                    <input id="service_number" type="text" name="service_number" value="{{ old('service_number') }}" inputmode="numeric" autocomplete="username" required autofocus>
                </div>
                <div class="field">
                    <label for="password">Password</label>
                    <input id="password" type="password" name="password" autocomplete="current-password" required>
                </div>
                <button class="btn" type="submit" style="width:100%;justify-content:center;padding:11px">Sign in</button>
            </form>
            <p class="muted small" style="margin-top:20px">Forgotten your password? Ask a Super Administrator to reset it.</p>
        </div>
    </div>
</div>
@endsection
