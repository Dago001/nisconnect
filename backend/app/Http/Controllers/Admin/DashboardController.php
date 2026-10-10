<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Call;
use App\Models\Channel;
use App\Models\Device;
use App\Models\Group;
use App\Models\Message;
use App\Models\PersonnelRecord;
use App\Models\Report;
use App\Models\SecurityEvent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $since = Carbon::now()->subDays(13)->startOfDay();

        $stats = [
            'personnel' => PersonnelRecord::count(),
            'accounts' => User::count(),
            'active' => User::where('account_state', User::STATE_ACTIVE)->count(),
            'suspended' => User::where('account_state', User::STATE_SUSPENDED)->count(),
            'online' => User::where('presence', 'online')->count(),
            'devices' => Device::where('status', Device::STATUS_ACTIVE)->count(),
            'groups' => Group::count(),
            'channels' => Channel::count(),
            'messages_today' => Message::where('created_at', '>=', Carbon::today())->count(),
            'calls_today' => Call::where('created_at', '>=', Carbon::today())->count(),
            'open_reports' => Report::whereIn('status', ['open', 'reviewing'])->count(),
            'security_24h' => SecurityEvent::whereIn('severity', ['warning', 'critical'])
                ->where('created_at', '>=', Carbon::now()->subDay())->count(),
        ];
        $coverage = $stats['personnel'] > 0 ? round($stats['accounts'] / $stats['personnel'] * 100) : 0;

        $charts = [
            'signups' => $this->daily(User::query(), $since),
            'messages' => $this->daily(Message::query(), $since),
        ];

        $recentSecurity = $user->hasPermission('security.view')
            ? SecurityEvent::orderByDesc('created_at')->limit(8)->get() : collect();
        $recentAudit = $user->hasPermission('audit.view')
            ? AuditLog::orderByDesc('created_at')->limit(10)->get() : collect();
        $actors = User::whereIn('id', $recentAudit->pluck('actor_id')->filter()->unique())
            ->pluck('display_name', 'id');

        return view('admin.dashboard', compact('stats', 'coverage', 'charts', 'recentSecurity', 'recentAudit', 'actors'));
    }

    /**
     * Counts per day for the last 14 days, zero-filled.
     *
     * @return array<string, int>
     */
    private function daily($query, Carbon $since): array
    {
        $rows = $query->where('created_at', '>=', $since)
            ->select(DB::raw('DATE(created_at) as d'), DB::raw('COUNT(*) as c'))
            ->groupBy('d')->pluck('c', 'd');

        $out = [];
        for ($day = $since->copy(); $day->lte(Carbon::today()); $day->addDay()) {
            $out[$day->format('d M')] = (int) ($rows[$day->toDateString()] ?? 0);
        }

        return $out;
    }
}
