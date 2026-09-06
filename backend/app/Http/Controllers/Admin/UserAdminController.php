<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\User;
use App\Services\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserAdminController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): View
    {
        $q = $request->query('q');
        $users = User::query()
            ->with('personnelRecord')
            ->when($q, fn ($query) => $query->where('service_number', 'ilike', "%$q%")
                ->orWhere('display_name', 'ilike', "%$q%"))
            ->orderBy('display_name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.users', compact('users', 'q'));
    }

    public function suspend(Request $request, User $user): RedirectResponse
    {
        $user->update(['account_state' => User::STATE_SUSPENDED]);
        $user->tokens()->delete();
        $this->audit->log('admin.user.suspended', actorId: $request->user()->id,
            resourceType: 'user', resourceId: $user->id);
        $this->audit->security('account_suspended', userId: $user->id, severity: 'warning');

        return back()->with('status', 'Account suspended.');
    }

    public function reactivate(Request $request, User $user): RedirectResponse
    {
        $user->update(['account_state' => User::STATE_ACTIVE]);
        $this->audit->log('admin.user.reactivated', actorId: $request->user()->id,
            resourceType: 'user', resourceId: $user->id);

        return back()->with('status', 'Account reactivated.');
    }

    public function revokeDevices(Request $request, User $user): RedirectResponse
    {
        $user->devices()->update(['status' => Device::STATUS_REVOKED]);
        $user->tokens()->delete();
        $this->audit->log('admin.user.devices_revoked', actorId: $request->user()->id,
            resourceType: 'user', resourceId: $user->id);

        return back()->with('status', 'All devices revoked.');
    }
}
