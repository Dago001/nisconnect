<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Call;
use App\Models\Channel;
use App\Models\Device;
use App\Models\Group;
use App\Models\Message;
use App\Models\SecurityEvent;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $stats = [
            'personnel' => \App\Models\PersonnelRecord::count(),
            'accounts' => User::count(),
            'active' => User::where('account_state', User::STATE_ACTIVE)->count(),
            'suspended' => User::where('account_state', User::STATE_SUSPENDED)->count(),
            'online' => User::where('presence', 'online')->count(),
            'devices' => Device::where('status', Device::STATUS_ACTIVE)->count(),
            'groups' => Group::count(),
            'channels' => Channel::count(),
            'messages' => Message::count(),
            'calls' => Call::count(),
        ];

        $recentSecurity = SecurityEvent::orderByDesc('created_at')->limit(10)->get();
        $recentAudit = AuditLog::orderByDesc('created_at')->limit(15)->get();

        return view('admin.dashboard', compact('stats', 'recentSecurity', 'recentAudit'));
    }
}
