<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Rules\AdminPassword;
use App\Services\Admin\TwoFactorService;
use App\Services\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/** The signed-in administrator's own account: password and two-factor authentication. */
class AccountController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly TwoFactorService $twoFactor,
    ) {}

    public function show(Request $request): View
    {
        $user = $request->user()->load('roles.role', 'personnelRecord');
        $activity = AuditLog::where('actor_id', $user->id)->orderByDesc('created_at')->limit(15)->get();

        return view('admin.account.show', compact('user', 'activity'));
    }

    public function showPassword(): View
    {
        return view('admin.account.password');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $user = $request->user();
        $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', 'different:current_password', AdminPassword::rule()],
        ]);

        if (! Hash::check($request->input('current_password'), (string) $user->password_hash)) {
            $this->audit->log('admin.password.change_failed', actorId: $user->id, result: 'failure');

            return back()->withErrors(['current_password' => 'Your current password is incorrect.']);
        }

        $user->forceFill([
            'password_hash' => Hash::make($request->input('password')),
            'password_changed_at' => now(),
            'must_change_password' => false,
        ])->save();
        $this->audit->log('admin.password.changed', actorId: $user->id);
        $this->audit->security('admin_password_changed', userId: $user->id);

        return redirect()->route('admin.account')->with('status', 'Your password has been changed.');
    }

    public function showTwoFactor(Request $request): View
    {
        $user = $request->user();
        $pendingSecret = $request->session()->get('admin_2fa_setup_secret');
        $qr = null;
        $url = null;
        if ($pendingSecret && ! $user->hasTwoFactorEnabled()) {
            $url = $this->twoFactor->otpauthUrl($user, $pendingSecret);
            $qr = $this->twoFactor->qrSvg($url);
        }

        return view('admin.account.two-factor', [
            'user' => $user,
            'pendingSecret' => $pendingSecret,
            'qr' => $qr,
            'recoveryCodes' => $request->session()->get('admin_2fa_recovery_codes'),
        ]);
    }

    /** Step 1: generate a secret and show the QR code. */
    public function enableTwoFactor(Request $request): RedirectResponse
    {
        if ($request->user()->hasTwoFactorEnabled()) {
            return redirect()->route('admin.account.two-factor');
        }
        $request->session()->put('admin_2fa_setup_secret', $this->twoFactor->generateSecret());

        return redirect()->route('admin.account.two-factor');
    }

    /** Step 2: confirm with a code from the app, then show recovery codes once. */
    public function confirmTwoFactor(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:10']]);
        $user = $request->user();
        $secret = $request->session()->get('admin_2fa_setup_secret');

        if (! $secret || ! $this->twoFactor->verify($secret, $request->input('code'))) {
            return back()->withErrors(['code' => 'That code did not match. Make sure your phone\'s time is correct and try again.']);
        }

        [$plain, $hashes] = $this->twoFactor->makeRecoveryCodes();
        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => $hashes,
            'two_factor_confirmed_at' => now(),
        ])->save();
        $request->session()->forget('admin_2fa_setup_secret');
        $request->session()->flash('admin_2fa_recovery_codes', $plain);
        $this->audit->log('admin.2fa.enabled', actorId: $user->id);
        $this->audit->security('admin_2fa_enabled', userId: $user->id);

        return redirect()->route('admin.account.two-factor')->with('status', 'Two-factor authentication is on.');
    }

    public function regenerateRecoveryCodes(Request $request): RedirectResponse
    {
        $request->validate(['current_password' => ['required', 'current_password']]);
        $user = $request->user();
        abort_unless($user->hasTwoFactorEnabled(), 404);

        [$plain, $hashes] = $this->twoFactor->makeRecoveryCodes();
        $user->forceFill(['two_factor_recovery_codes' => $hashes])->save();
        $request->session()->flash('admin_2fa_recovery_codes', $plain);
        $this->audit->log('admin.2fa.recovery_regenerated', actorId: $user->id);

        return redirect()->route('admin.account.two-factor')->with('status', 'New recovery codes generated. The old ones no longer work.');
    }

    public function disableTwoFactor(Request $request): RedirectResponse
    {
        $request->validate(['current_password' => ['required', 'current_password']]);
        $user = $request->user();
        $user->forceFill([
            'two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null,
        ])->save();
        $this->audit->log('admin.2fa.disabled', actorId: $user->id);
        $this->audit->security('admin_2fa_disabled', userId: $user->id, severity: 'warning');

        return redirect()->route('admin.account.two-factor')->with('status', 'Two-factor authentication is off.');
    }
}
