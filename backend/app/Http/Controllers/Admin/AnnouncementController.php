<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Services\Admin\AnnouncementService;
use App\Services\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    public const AUDIENCE_LABELS = [
        'all' => 'All officers', 'directorate' => 'Directorate', 'command' => 'Command', 'zone' => 'Zone', 'rank' => 'Rank',
    ];

    public function __construct(
        private readonly AnnouncementService $announcements,
        private readonly AuditLogger $audit,
    ) {}

    public function index(): View
    {
        return view('admin.announcements.index', [
            'history' => Announcement::with('sender')->orderByDesc('created_at')->paginate(20),
            'options' => $this->announcements->audienceOptions(),
            'labels' => self::AUDIENCE_LABELS,
            'everyone' => $this->announcements->recipients('all', null)->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:2000'],
            'priority' => ['required', 'in:normal,urgent'],
            'audience' => ['required', 'string', 'max:300'],
        ], [
            'title.required' => 'Give the announcement a title.',
            'title.max' => 'Keep the title to 120 characters or fewer.',
            'body.required' => 'Write the announcement message.',
            'body.max' => 'Keep the message to 2,000 characters or fewer.',
            'audience.required' => 'Choose who should receive this announcement.',
        ]);

        // Audience arrives as "all" or "<type>|<value>" from the grouped select.
        [$type, $value] = array_pad(explode('|', $data['audience'], 2), 2, null);
        $options = $this->announcements->audienceOptions();
        if (! array_key_exists($type, AnnouncementService::AUDIENCES)
            || ($type !== 'all' && ! ($options[$type] ?? collect())->contains($value))) {
            throw ValidationException::withMessages(['audience' => 'Choose an audience from the list.']);
        }
        $value = $type === 'all' ? null : $value;

        if ($this->announcements->recipients($type, $value)->count() === 0) {
            return back()->withInput()->with('warning', 'Nobody with an active account is in that audience yet, so nothing was sent.');
        }

        $announcement = $this->announcements->send([
            'title' => $data['title'],
            'body' => $data['body'],
            'priority' => $data['priority'],
            'audience_type' => $type,
            'audience_value' => $value,
        ], $request->user());

        $this->audit->log('announcement.sent', actorId: $request->user()->id, resourceType: 'announcement',
            resourceId: $announcement->id, metadata: [
                'audience_type' => $type, 'audience_value' => $value,
                'priority' => $announcement->priority, 'recipients' => $announcement->recipients_count,
            ]);

        return redirect()->route('admin.announcements.index')
            ->with('status', 'Announcement sent to '.number_format($announcement->recipients_count).' '
                .($announcement->recipients_count === 1 ? 'officer' : 'officers').'.');
    }
}
