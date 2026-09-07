<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Services\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): View
    {
        $status = $request->query('status', 'open');
        $reports = Report::query()
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.reports', compact('reports', 'status'));
    }

    public function action(Request $request, Report $report): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', 'in:reviewing,actioned,dismissed']]);
        $report->update([
            'status' => $data['status'],
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => Carbon::now(),
        ]);
        $this->audit->log('report.reviewed', actorId: $request->user()->id,
            resourceType: 'report', resourceId: $report->id, metadata: ['status' => $data['status']]);

        return back()->with('status', 'Report updated.');
    }
}
