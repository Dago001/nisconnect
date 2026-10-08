@extends('admin.layout')
@section('title', 'Two-factor authentication')
@section('content')
<div class="auth">
    <div class="auth-hero">
        <div style="display:flex;gap:12px;align-items:center">
            <img src="{{ asset('portal-assets/nis-logo.jpg') }}" alt="" width="48" height="48" style="border-radius:10px;background:#fff">
            <strong style="font-size:18px">NISconnect</strong>
        </div>
        <h1>Confirm it's you</h1>
        <div></div>
    </div>
    <div class="auth-panel">
        <div class="auth-box">
            <h2 style="font-size:22px">Two-factor authentication</h2>
            <p class="muted" style="margin-top:0">Enter the 6-digit code from your authenticator app.</p>
            @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
            <form method="POST" action="{{ route('admin.two-factor.verify') }}">
                @csrf
                <div class="field">
                    <label for="code">Authentication code</label>
                    <input id="code" type="text" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" autofocus style="font-size:20px;letter-spacing:.3em;text-align:center">
                </div>
                <button class="btn" type="submit" style="width:100%;justify-content:center;padding:11px">Verify</button>
            </form>
            <details style="margin-top:20px">
                <summary class="muted" style="cursor:pointer">Lost your phone? Use a recovery code</summary>
                <form method="POST" action="{{ route('admin.two-factor.verify') }}" style="margin-top:10px">
                    @csrf
                    <div class="field"><label for="recovery_code">Recovery code</label>
                        <input id="recovery_code" type="text" name="recovery_code" placeholder="XXXXX-XXXXX" autocomplete="off"></div>
                    <button class="btn btn-ghost" type="submit">Use recovery code</button>
                </form>
            </details>
            <p class="small" style="margin-top:20px"><a href="{{ route('admin.login') }}">Back to sign in</a></p>
        </div>
    </div>
</div>
@endsection
