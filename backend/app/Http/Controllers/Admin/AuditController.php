<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\SecurityEvent;
use Illuminate\View\View;

class AuditController extends Controller
{
    public function auditLogs(): View
    {
        $logs = AuditLog::orderByDesc('created_at')->paginate(50);

        return view('admin.audit', compact('logs'));
    }

    public function securityEvents(): View
    {
        $events = SecurityEvent::orderByDesc('created_at')->paginate(50);

        return view('admin.security', compact('events'));
    }
}
