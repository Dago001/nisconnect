<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function showLogin(): View
    {
        return view('admin.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'service_number' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('service_number', $data['service_number'])->first();

        if (! $user || empty($user->password_hash) || ! Hash::check($data['password'], $user->password_hash)) {
            $this->audit->log('admin.login.failed', result: 'failure',
                metadata: ['service_number' => $data['service_number']]);

            return back()->withErrors(['service_number' => 'Invalid credentials.']);
        }

        // Only privileged roles may enter the portal.
        $adminRoles = ['super_admin', 'nis_admin', 'directorate_admin', 'group_admin', 'security_admin'];
        if (! collect($adminRoles)->contains(fn ($r) => $user->hasRole($r))) {
            $this->audit->log('admin.login.denied', actorId: $user->id, result: 'denied');

            return back()->withErrors(['service_number' => 'This account has no administrative access.']);
        }

        Auth::login($user, true);
        $request->session()->regenerate();
        $this->audit->log('admin.login.success', actorId: $user->id);

        return redirect()->route('admin.dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        $this->audit->log('admin.logout', actorId: $request->user()?->id);
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
