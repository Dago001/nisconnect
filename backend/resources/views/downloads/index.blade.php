<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <meta name="description" content="Download the NISconnect app for Nigeria Immigration Service officers.">
    <title>Get NISconnect · Nigeria Immigration Service</title>
    <link rel="icon" href="{{ asset('portal-assets/nis-logo.jpg') }}">
    <style>
    :root{
      --brand:#064A28;--brand-2:#0B6B3A;--brand-soft:#E6F4EC;--gold:#C9A227;
      --bg:#F4F6F5;--surface:#FFFFFF;--surface-2:#F8FAF9;--border:#E2E7E3;--text:#14201A;--muted:#5F6B64;
      --link:#0B6B3A;--shadow:0 1px 2px rgba(16,24,20,.06),0 4px 16px rgba(16,24,20,.06);
    }
    @media (prefers-color-scheme: dark){
      :root{--bg:#0E1512;--surface:#151E1A;--surface-2:#1B2621;--border:#26332D;--text:#E6EEE9;--muted:#9AA8A0;
        --brand-soft:#123524;--link:#5FC28B;--shadow:0 1px 2px rgba(0,0,0,.4)}
    }
    *{box-sizing:border-box}
    html,body{margin:0}
    body{font-family:Arial,"Liberation Sans",Helvetica,sans-serif;font-size:15px;line-height:1.55;color:var(--text);background:var(--bg);-webkit-text-size-adjust:100%}
    a{color:var(--link)}
    .icon{width:20px;height:20px;flex:none;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
    .wrap{max-width:960px;margin:0 auto;padding:0 20px}
    .hero{background:linear-gradient(135deg,#064A28 0%,#0B6B3A 60%,#0E7A43 100%);color:#fff;padding:28px 0 88px}
    .brand{display:flex;gap:12px;align-items:center;color:#fff;text-decoration:none}
    .brand img{border-radius:10px;background:#fff;width:44px;height:44px}
    .brand strong{font-size:18px;display:block;line-height:1.2}
    .brand small{opacity:.8;font-size:13px}
    .hero h1{font-size:34px;line-height:1.15;margin:36px 0 10px;letter-spacing:-.01em}
    .hero p{margin:0;max-width:560px;opacity:.9;font-size:16px}
    .cards{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:20px;margin-top:-60px}
    .card{background:var(--surface);border:1px solid var(--border);border-radius:16px;box-shadow:var(--shadow);padding:24px;display:flex;flex-direction:column}
    .card-head{display:flex;gap:12px;align-items:center;margin-bottom:14px}
    .badge-icon{width:44px;height:44px;border-radius:12px;background:var(--brand-soft);color:var(--link);display:grid;place-items:center}
    .badge-icon .icon{width:24px;height:24px}
    .card h2{font-size:20px;margin:0;line-height:1.2}
    .card .sub{color:var(--muted);font-size:13px}
    .btn{display:flex;gap:10px;align-items:center;justify-content:center;background:var(--brand);color:#fff;text-decoration:none;font-weight:700;font-size:17px;padding:15px 18px;border-radius:12px;border:1px solid transparent;min-height:52px}
    .btn:hover{background:#05391F}
    .btn:focus-visible,.btn-ghost:focus-visible,a:focus-visible{outline:3px solid var(--gold);outline-offset:2px}
    .btn-ghost{display:flex;gap:8px;align-items:center;justify-content:center;background:transparent;color:var(--text);border:1px solid var(--border);text-decoration:none;font-weight:700;padding:12px 16px;border-radius:12px;min-height:48px}
    .btn-ghost:hover{background:var(--surface-2)}
    @media (prefers-color-scheme: dark){.btn{background:#0B6B3A}.btn:hover{background:#0E7A43}}
    .meta{display:flex;flex-wrap:wrap;gap:6px 16px;margin:12px 0 0;padding:0;list-style:none;color:var(--muted);font-size:13px}
    .meta strong{color:var(--text);font-weight:600}
    .sha{margin-top:10px;font-size:12px;color:var(--muted);word-break:break-all}
    .sha code{font-family:ui-monospace,Menlo,Consolas,monospace;font-size:11.5px;color:var(--text)}
    .alt{margin-top:12px;font-size:14px}
    .steps{margin:18px 0 0;padding:16px 16px 16px 36px;background:var(--surface-2);border:1px solid var(--border);border-radius:12px;font-size:14px}
    .steps li+li{margin-top:6px}
    .steps-title{font-weight:700;font-size:14px;margin:16px 0 -8px}
    .soon{background:var(--surface-2);border:1px dashed var(--border);border-radius:12px;padding:18px;text-align:center;color:var(--muted)}
    .soon strong{display:block;color:var(--text);font-size:16px;margin-bottom:4px}
    .notes{margin:14px 0 0;font-size:14px;color:var(--muted);white-space:pre-line}
    .spacer{flex:1}
    .browser{margin:28px 0 0;display:flex;gap:14px;align-items:center;justify-content:space-between;flex-wrap:wrap;background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:18px 24px}
    .browser p{margin:0;color:var(--muted)}
    .browser strong{color:var(--text)}
    footer{color:var(--muted);font-size:12px;text-align:center;padding:28px 20px 36px}
    @media (max-width:760px){
      .cards{grid-template-columns:1fr}
      .hero h1{font-size:28px;margin-top:28px}
    }
    @media (max-width:420px){
      .wrap{padding:0 16px}
      .card{padding:18px}
      .hero{padding-bottom:80px}
      .btn{font-size:16px}
      .browser{padding:16px 18px}
    }
    </style>
</head>
<body>
<header class="hero">
    <div class="wrap">
        <a class="brand" href="{{ route('downloads.index') }}">
            <img src="{{ asset('portal-assets/nis-logo.jpg') }}" alt="" width="44" height="44">
            <span><strong>NISconnect</strong><small>Nigeria Immigration Service</small></span>
        </a>
        <h1>Get NISconnect</h1>
        <p>Secure messaging and calls for NIS officers. Install the app on your phone, then sign in with your Service Number.</p>
    </div>
</header>

<main class="wrap">
    <div class="cards">
        <section class="card" aria-labelledby="android-title">
            <div class="card-head">
                <span class="badge-icon"><svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="2" width="14" height="20" rx="2"/><path d="M12 18h.01"/></svg></span>
                <div><h2 id="android-title">Android</h2><div class="sub">Phones and tablets running Android</div></div>
            </div>

            @if($standard || $legacy)
                @php $main = $standard ?? $legacy; @endphp
                <a class="btn" href="{{ route('downloads.android', $main->variant) }}" download>
                    <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>
                    Download for Android
                </a>
                <ul class="meta">
                    <li>Version <strong>{{ $main->version }}</strong></li>
                    <li><strong>{{ $main->sizeInMb() }} MB</strong></li>
                    <li>Released <strong>{{ $main->created_at->format('j M Y') }}</strong></li>
                    @if(! $standard)<li>For older (32-bit) phones</li>@endif
                </ul>
                <div class="sha">SHA-256: <code>{{ $main->sha256 }}</code></div>
                @if($main->notes)<p class="notes">{{ $main->notes }}</p>@endif
                @if($standard && $legacy)
                    <p class="alt"><a href="{{ route('downloads.android', 'legacy') }}" download>Older phone? Download the 32-bit version</a>
                        <span class="sub">({{ $legacy->version }}, {{ $legacy->sizeInMb() }} MB)</span></p>
                @endif

                <p class="steps-title">How to install</p>
                <ol class="steps">
                    <li>Open the downloaded file (tap the notification, or find it in <strong>Downloads</strong>).</li>
                    <li>If asked, allow installing apps from this source (your browser or Files app), then go back.</li>
                    <li>Tap <strong>Install</strong>, then open NISconnect and sign in with your Service Number.</li>
                    <li>If you see “App not installed”, uninstall any older or test version of NISconnect first, then try again.</li>
                </ol>
            @else
                <div class="soon"><strong>The Android app will be available here soon</strong>Check back later, or use NISconnect in your browser for now.</div>
            @endif
            <div class="spacer"></div>
        </section>

        <section class="card" aria-labelledby="ios-title">
            <div class="card-head">
                <span class="badge-icon"><svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="6" y="2" width="12" height="20" rx="3"/><path d="M11 5h2"/></svg></span>
                <div><h2 id="ios-title">iPhone</h2><div class="sub">iPhone and iPad</div></div>
            </div>
            <p style="margin:0 0 4px">There's no App Store version yet. You can add the NISconnect web app to your Home Screen and use it like an app:</p>
            <ol class="steps">
                <li>Open this site in <strong>Safari</strong>.</li>
                <li>Tap the <strong>Share</strong> button
                    <svg class="icon" viewBox="0 0 24 24" aria-hidden="true" style="width:16px;height:16px;vertical-align:-3px"><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8M16 6l-4-4-4 4M12 2v13"/></svg>.</li>
                <li>Choose <strong>Add to Home Screen</strong>, then tap <strong>Add</strong>.</li>
                <li>Open NISconnect from your Home Screen and sign in with your Service Number.</li>
            </ol>
            <div class="spacer"></div>
            <a class="btn-ghost" href="/" style="margin-top:18px">
                <svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                Open the web app
            </a>
        </section>
    </div>

    <div class="browser">
        <p><strong>On a computer?</strong> You can use NISconnect in any modern browser.</p>
        <a class="btn-ghost" href="/">Use in your browser</a>
    </div>
</main>

<footer>
    NISconnect is for authorised Nigeria Immigration Service personnel only.<br>
    Only install the app from this page. Check the SHA-256 fingerprint if you received the file another way.
</footer>
</body>
</html>
