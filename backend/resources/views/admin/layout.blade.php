<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>NISconnect Admin — @yield('title', 'Portal')</title>
    <style>
        :root { --green:#0B6B3A; --dark:#064A28; --light:#E6F4EC; --border:#E2E7E3; --grey:#6B7770; --text:#14201A; }
        * { box-sizing:border-box; }
        body { margin:0; font-family:Arial, Helvetica, sans-serif; color:var(--text); background:#F5F7F5; }
        header { background:var(--dark); color:#fff; padding:12px 20px; display:flex; align-items:center; gap:12px; }
        header h1 { font-size:18px; margin:0; }
        nav a { color:#fff; text-decoration:none; margin-right:16px; font-size:14px; }
        .container { max-width:1100px; margin:24px auto; padding:0 20px; }
        .cards { display:grid; grid-template-columns:repeat(auto-fill,minmax(160px,1fr)); gap:12px; }
        .card { background:#fff; border:1px solid var(--border); border-radius:12px; padding:16px; }
        .card .n { font-size:26px; font-weight:bold; color:var(--green); }
        .card .l { font-size:12px; color:var(--grey); }
        table { width:100%; border-collapse:collapse; background:#fff; border:1px solid var(--border); border-radius:12px; overflow:hidden; }
        th, td { text-align:left; padding:10px 12px; border-bottom:1px solid var(--border); font-size:14px; }
        th { background:var(--light); color:var(--dark); }
        .btn { background:var(--green); color:#fff; border:0; border-radius:8px; padding:8px 12px; font-family:inherit; cursor:pointer; font-size:13px; }
        .btn.warn { background:#C62828; }
        .btn.grey { background:#6B7770; }
        input[type=text], input[type=password] { padding:10px; border:1px solid var(--border); border-radius:8px; width:100%; font-family:inherit; }
        .status { background:var(--light); color:var(--dark); padding:8px 12px; border-radius:8px; margin-bottom:12px; }
        .err { color:#C62828; font-size:13px; }
        .pill { font-size:12px; padding:2px 8px; border-radius:10px; }
        .pill.active { background:#E6F4EC; color:#2E7D32; }
        .pill.suspended { background:#fdeaea; color:#C62828; }
    </style>
</head>
<body>
    @auth
    <header>
        <strong style="font-size:20px;">NISconnect</strong>
        <nav>
            <a href="{{ route('admin.dashboard') }}">Dashboard</a>
            <a href="{{ route('admin.users') }}">Users</a>
            <a href="{{ route('admin.org') }}">Organisation</a>
            <a href="{{ route('admin.reports') }}">Reports</a>
            <a href="{{ route('admin.audit') }}">Audit</a>
            <a href="{{ route('admin.security') }}">Security</a>
        </nav>
        <form method="POST" action="{{ route('admin.logout') }}" style="margin-left:auto;">
            @csrf <button class="btn grey" type="submit">Sign out</button>
        </form>
    </header>
    @endauth
    <div class="container">
        @if(session('status'))<div class="status">{{ session('status') }}</div>@endif
        @yield('content')
    </div>
</body>
</html>
