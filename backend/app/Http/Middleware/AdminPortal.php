<?php

namespace App\Http\Middleware;

use App\Services\Admin\SettingsService;
use App\Services\Support\AuditLogger;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards every authenticated admin page:
 *  - the account must still be active and hold portal access,
 *  - idle sessions expire (setting: admin_session_timeout),
 *  - administrators must set up 2FA when the policy requires it,
 *  - temporary or expired passwords must be changed first.
 */
class AdminPortal
{
    /** Routes reachable while a password change or 2FA setup is pending. */
    private const ACCOUNT_ROUTES = [
        'admin.account.password', 'admin.account.password.update',
        'admin.account.two-factor', 'admin.account.two-factor.enable',
        'admin.account.two-factor.confirm', 'admin.logout',
    ];

    public function __construct(
        private readonly SettingsService $settings,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->guest(route('admin.login'));
        }

        if (! $user->canAccessAdminPortal()) {
            $this->signOut($request);

            return redirect()->route('admin.login')
                ->withErrors(['service_number' => 'Your administrative access has been withdrawn.']);
        }

        $session = $request->session();
        $timeout = (int) $this->settings->get('admin_session_timeout') * 60;
        $last = (int) $session->get('admin_last_activity', 0);
        if ($last > 0 && time() - $last > $timeout) {
            $this->audit->log('admin.session.expired', actorId: $user->id);
            $this->signOut($request);

            return redirect()->route('admin.login')
                ->withErrors(['service_number' => 'You were signed out after a period of inactivity.']);
        }
        $session->put('admin_last_activity', time());

        $route = $request->route()?->getName();
        if (! in_array($route, self::ACCOUNT_ROUTES, true)) {
            if ($user->must_change_password || $this->passwordExpired($user)) {
                return redirect()->route('admin.account.password')
                    ->with('warning', 'Please choose a new password before continuing.');
            }
            if ($this->settings->get('admin_require_2fa') && ! $user->hasTwoFactorEnabled()) {
                return redirect()->route('admin.account.two-factor')
                    ->with('warning', 'Two-factor authentication is required for administrators. Set it up to continue.');
            }
        }

        return $next($request);
    }

    private function passwordExpired($user): bool
    {
        $days = (int) $this->settings->get('admin_password_max_age_days');
        if ($days <= 0) {
            return false;
        }
        $changed = $user->password_changed_at ?? $user->created_at;

        return $changed !== null && $changed->lt(now()->subDays($days));
    }

    private function signOut(Request $request): void
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}
