<?php

use App\Http\Middleware\AdminPortal;
use App\Http\Middleware\AdminSecurityHeaders;
use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\EnsureRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    // The apps authenticate private channels with their API token.
    ->withBroadcasting(__DIR__.'/../routes/channels.php', ['middleware' => ['auth:sanctum']])
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role' => EnsureRole::class,
            'perm' => EnsurePermission::class,
            'admin.portal' => AdminPortal::class,
            'admin.headers' => AdminSecurityHeaders::class,
        ]);

        // Guests hitting a guarded web route go to the admin login (API routes
        // stay stateless and return 401 via Sanctum).
        $middleware->redirectGuestsTo(fn () => route('admin.login'));
        $middleware->redirectUsersTo(fn () => route('admin.dashboard'));

        // Behind a load balancer / PaaS router (e.g. Render), trust its
        // X-Forwarded-* headers so client IPs (rate limits, audit) and https
        // are seen correctly. "*" or a comma-separated list; unset = trust none.
        if ($proxies = env('TRUSTED_PROXIES')) {
            $middleware->trustProxies(at: $proxies === '*' ? '*' : explode(',', $proxies));
        }
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
