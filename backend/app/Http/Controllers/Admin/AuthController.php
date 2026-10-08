<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Admin\TwoFactorService;
use App\Services\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * Administration portal sign-in: Service Number + password, then a TOTP code
 * (or a recovery code) when two-factor authentication is enabled.
 */
class AuthController extends Controller
{
    private const PENDING_KEY = 'admin_2fa_pending';

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly TwoFactorService $twoFactor,
    ) {}

    public function showLogin(): View
    {
        return view('admin.auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'service_number' => ['required', 'string', 'regex:/^[0-9]{1,20}$/'],
            'password' => ['required', 'string', 'max:200'],
        ], ['service_number.regex' => 'Service Numbers contain digits only.']);

        $user = User::where('service_number', $data['service_number'])->first();
        // Always run a hash check so response time doesn't reveal whether the account exists.
        $valid = Hash::check($data['password'], $user?->password_hash ?: $this->dummyHash());

        if (! $user || empty($user->password_hash) || ! $valid) {
            $this->audit->log('admin.login.failed', actorId: $user?->id, result: 'failure',
                metadata: ['service_number' => $data['service_number']]);
            $this->audit->security('admin_login_failed', userId: $user?->id, severity: 'warning');

            return back()->withInput($request->only('service_number'))
                ->withErrors(['service_number' => 'The Service Number or password is incorrect.']);
        }

        if (! $user->canAccessAdminPortal()) {
            $this->audit->log('admin.login.denied', actorId: $user->id, result: 'denied');

            return back()->withInput($request->only('service_number'))
                ->withErrors(['service_number' => 'This account does not have administrative access.']);
        }

        if ($user->hasTwoFactorEnabled()) {
            $request->session()->put(self::PENDING_KEY, ['id' => $user->id, 'at' => time()]);

            return redirect()->route('admin.two-factor.challenge');
        }

        return $this->completeLogin($request, $user, 'password');
    }

    public function showChallenge(Request $request): View|RedirectResponse
    {
        if (! $this->pendingUser($request)) {
            return redirect()->route('admin.login');
        }

        return view('admin.auth.two-factor');
    }

    public function challenge(Request $request): RedirectResponse
    {
        $user = $this->pendingUser($request);
        if (! $user) {
            return redirect()->route('admin.login')->withErrors(['service_number' => 'Please sign in again.']);
        }

        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:20'],
            'recovery_code' => ['nullable', 'string', 'max:20'],
        ]);

        $ok = false;
        $method = 'totp';
        if (! empty($data['code'])) {
            $ok = $this->twoFactor->verify($user->two_factor_secret, $data['code']);
        } elseif (! empty($data['recovery_code'])) {
            $ok = $this->twoFactor->useRecoveryCode($user, $data['recovery_code']);
            $method = 'recovery_code';
        }

        if (! $ok) {
            $this->audit->log('admin.2fa.failed', actorId: $user->id, result: 'failure');
            $this->audit->security('admin_2fa_failed', userId: $user->id, severity: 'warning');

            return back()->withErrors(['code' => 'That code is not valid. Check your authenticator app and try again.']);
        }

        $request->session()->forget(self::PENDING_KEY);
        if ($method === 'recovery_code') {
            $this->audit->security('admin_recovery_code_used', userId: $user->id, severity: 'warning');
        }

        return $this->completeLogin($request, $user, $method);
    }

    public function logout(Request $request): RedirectResponse
    {
        $this->audit->log('admin.logout', actorId: $request->user()?->id);
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login')->with('status', 'You have been signed out.');
    }

    private function completeLogin(Request $request, User $user, string $method): RedirectResponse
    {
        // No "remember me" for administrators: sessions end with the browser or idle timeout.
        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        $request->session()->put('admin_last_activity', time());
        $user->forceFill(['last_admin_login_at' => now(), 'last_admin_login_ip' => $request->ip()])->save();
        $this->audit->log('admin.login.success', actorId: $user->id, metadata: ['method' => $method]);

        return redirect()->intended(route('admin.dashboard'));
    }

    private function dummyHash(): string
    {
        static $hash = null;

        return $hash ??= Hash::make('nisconnect-timing-equaliser');
    }

    private function pendingUser(Request $request): ?User
    {
        $pending = $request->session()->get(self::PENDING_KEY);
        // The password step is valid for 5 minutes.
        if (! is_array($pending) || time() - ($pending['at'] ?? 0) > 300) {
            return null;
        }

        return User::find($pending['id']);
    }
}
