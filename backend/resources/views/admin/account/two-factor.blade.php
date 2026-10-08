@extends('admin.layout')
@section('title', 'Two-factor authentication')
@section('inline-errors', '1')
@section('content')
<x-admin.page-header title="Two-factor authentication" subtitle="Protect your administrator account with a code from your phone." :crumbs="['My account' => route('admin.account')]" />

@if($recoveryCodes)
<div class="card card-body" style="max-width:640px;margin-bottom:16px;border-color:var(--warning)">
    <h2>Save your recovery codes</h2>
    <p class="muted">Each code can be used once if you lose your phone. Store them somewhere safe. They won't be shown again.</p>
    <div class="mono" style="display:grid;grid-template-columns:repeat(2,1fr);gap:6px;font-size:15px;background:var(--surface-2);padding:14px;border-radius:10px">
        @foreach($recoveryCodes as $c)<span>{{ $c }}</span>@endforeach
    </div>
</div>
@endif

<div class="card card-body" style="max-width:640px">
@if($user->hasTwoFactorEnabled())
    <p><x-admin.badge value="enabled" /> Two-factor authentication is on since {{ $user->two_factor_confirmed_at->format('d M Y') }}.
        {{ count($user->two_factor_recovery_codes ?? []) }} recovery codes left.</p>
    <div class="grid grid-2" style="margin-top:12px">
        <form method="POST" action="{{ route('admin.account.two-factor.recovery') }}">
            @csrf
            <h3>New recovery codes</h3>
            <div class="field"><label for="rc_pw">Current password</label><input id="rc_pw" type="password" name="current_password" required></div>
            <button class="btn btn-ghost" type="submit">Generate new codes</button>
        </form>
        <form method="POST" action="{{ route('admin.account.two-factor.disable') }}" data-confirm="Turn off two-factor authentication?" data-confirm-body="Your account will be protected by your password only." data-confirm-ok="Turn off">
            @csrf @method('DELETE')
            <h3>Turn off</h3>
            <div class="field"><label for="dis_pw">Current password</label><input id="dis_pw" type="password" name="current_password" required></div>
            <button class="btn btn-danger" type="submit">Turn off two-factor</button>
        </form>
    </div>
    @error('current_password')<div class="alert alert-danger" style="margin-top:12px">{{ $message }}</div>@enderror
@elseif($pendingSecret)
    <ol style="padding-left:18px">
        <li>Install an authenticator app (Google Authenticator, Microsoft Authenticator or Authy).</li>
        <li>Scan this QR code, or enter the key manually.</li>
        <li>Enter the 6-digit code the app shows.</li>
    </ol>
    <div style="display:flex;gap:20px;flex-wrap:wrap;align-items:center">
        <div style="background:#fff;padding:10px;border-radius:10px;width:220px">{!! $qr !!}</div>
        <div><div class="muted small">Setup key</div><div class="mono" style="font-size:15px;word-break:break-all;max-width:280px">{{ trim(chunk_split($pendingSecret, 4, ' ')) }}</div></div>
    </div>
    <form method="POST" action="{{ route('admin.account.two-factor.confirm') }}" style="margin-top:16px;max-width:280px">
        @csrf
        <div class="field"><label for="code">Code from the app</label>
            <input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required autofocus>
            @error('code')<div class="error">{{ $message }}</div>@enderror</div>
        <button class="btn" type="submit">Turn on</button>
    </form>
@else
    <p>Two-factor authentication is <strong>off</strong>. When it's on, signing in needs your password and a code from your phone.</p>
    <form method="POST" action="{{ route('admin.account.two-factor.enable') }}">@csrf
        <button class="btn" type="submit"><x-admin.icon name="key" /> Set up two-factor authentication</button></form>
@endif
</div>
@endsection
