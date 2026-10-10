@php
    $me = auth()->user();
    $nav = [
        'Overview' => [
            ['admin.dashboard', 'Dashboard', 'dashboard', 'dashboard.view', 'admin.dashboard'],
        ],
        'People' => [
            ['admin.officers.index', 'Officers', 'users', 'officers.view', 'admin.officers.*'],
            ['admin.personnel.index', 'Personnel records', 'id', 'personnel.view', 'admin.personnel.*'],
            ['admin.administrators.index', 'Administrators', 'shield', 'admins.manage', 'admin.administrators.*'],
            ['admin.roles.index', 'Roles & permissions', 'key', 'roles.manage', 'admin.roles.*'],
        ],
        'Organisation' => [
            ['admin.org.index', 'Structure', 'building', 'org.manage', 'admin.org.*'],
            ['admin.groups.index', 'Groups', 'group', 'groups.manage', 'admin.groups.*'],
            ['admin.channels.index', 'Channels', 'megaphone', 'channels.manage', 'admin.channels.*'],
            ['admin.announcements.index', 'Announcements', 'bell', 'announcements.send', 'admin.announcements.*'],
        ],
        'Safety & security' => [
            ['admin.reports.index', 'Reports', 'flag', 'reports.review', 'admin.reports.*'],
            ['admin.devices.index', 'Devices', 'phone', 'devices.view', 'admin.devices.*'],
            ['admin.audit.index', 'Audit log', 'list', 'audit.view', 'admin.audit.*'],
            ['admin.security.index', 'Security events', 'alert', 'security.view', 'admin.security.*'],
        ],
        'System' => [
            ['admin.settings.index', 'Settings', 'cog', 'settings.manage', 'admin.settings.*'],
            ['admin.system.index', 'System health', 'activity', 'system.view', 'admin.system.*'],
        ],
    ];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Portal') · NISconnect Admin</title>
    <link rel="icon" href="{{ asset('portal-assets/nis-logo.jpg') }}">
    @include('admin.partials.styles')
</head>
<body>
@auth
<div class="shell">
    <aside class="sidebar" id="sidebar" aria-label="Main navigation">
        <a href="{{ route('admin.dashboard') }}" class="brand">
            <img src="{{ asset('portal-assets/nis-logo.jpg') }}" alt="" width="36" height="36">
            <span><strong>NISconnect</strong><small>Administration</small></span>
        </a>
        <nav>
            @foreach($nav as $section => $items)
                @php $visible = array_filter($items, fn ($i) => $me->hasPermission($i[3])); @endphp
                @if($visible)
                    <div class="nav-section">{{ $section }}</div>
                    @foreach($visible as [$route, $label, $icon, $perm, $pattern])
                        <a href="{{ route($route) }}" class="nav-link {{ request()->routeIs($pattern) ? 'active' : '' }}"
                           @if(request()->routeIs($pattern)) aria-current="page" @endif>
                            <x-admin.icon :name="$icon" /> <span>{{ $label }}</span>
                        </a>
                    @endforeach
                @endif
            @endforeach
        </nav>
        <div class="sidebar-foot">v{{ config('app.version', '1.0.0') }} · {{ app()->environment() }}</div>
    </aside>
    <div class="backdrop" data-close-sidebar></div>

    <div class="main">
        <header class="topbar">
            <button class="icon-btn menu-btn" type="button" data-open-sidebar aria-label="Open menu"><x-admin.icon name="menu" /></button>
            @if($me->hasPermission('officers.view'))
            <form class="topsearch" method="GET" action="{{ route('admin.officers.index') }}" role="search">
                <x-admin.icon name="search" />
                <input type="search" name="q" placeholder="Find an officer by name or Service Number" aria-label="Find an officer">
            </form>
            @endif
            <div class="topbar-right">
                <button class="icon-btn" type="button" data-theme-toggle aria-label="Toggle dark mode"><x-admin.icon name="moon" /></button>
                <details class="usermenu">
                    <summary>
                        <span class="avatar">{{ \Illuminate\Support\Str::of($me->display_name ?: 'A')->explode(' ')->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('') }}</span>
                        <span class="who"><strong>{{ $me->display_name }}</strong><small>{{ $me->service_number }}</small></span>
                    </summary>
                    <div class="menu">
                        <a href="{{ route('admin.account') }}"><x-admin.icon name="user" /> My account</a>
                        <a href="{{ route('admin.account.two-factor') }}"><x-admin.icon name="key" /> Two-factor authentication</a>
                        <form method="POST" action="{{ route('admin.logout') }}">@csrf
                            <button type="submit"><x-admin.icon name="logout" /> Sign out</button>
                        </form>
                    </div>
                </details>
            </div>
        </header>

        <main class="content" id="content">
            @foreach(['status' => 'success', 'warning' => 'warning', 'error' => 'danger'] as $key => $tone)
                @if(session($key))
                    <div class="alert alert-{{ $tone }}" role="status" data-autodismiss>{{ session($key) }}</div>
                @endif
            @endforeach
            @if($errors->any() && ! View::hasSection('inline-errors'))
                <div class="alert alert-danger" role="alert">
                    @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
                </div>
            @endif
            @yield('content')
        </main>
    </div>
</div>

<dialog id="confirm-dialog" class="dialog">
    <form method="dialog">
        <h3 data-confirm-title>Are you sure?</h3>
        <p data-confirm-body class="muted"></p>
        <label data-confirm-reason-wrap hidden>Reason <span class="muted">(recorded in the audit log)</span>
            <textarea data-confirm-reason rows="3" maxlength="500"></textarea>
        </label>
        <div class="dialog-actions">
            <button class="btn btn-ghost" value="cancel">Cancel</button>
            <button class="btn btn-danger" value="ok" data-confirm-ok>Confirm</button>
        </div>
    </form>
</dialog>
@else
    @yield('content')
@endauth
@include('admin.partials.scripts')
</body>
</html>
