<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PersonnelRecord;
use App\Services\Admin\PersonnelAdminService;
use App\Services\Support\AuditLogger;
use App\Support\CsvExport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Personnel records: the local mirror of the authorised NIS personnel source
 * that Service Numbers are verified against at registration.
 */
class PersonnelController extends Controller
{
    public function __construct(
        private readonly PersonnelAdminService $personnel,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request): View
    {
        $records = $this->filtered($request)
            ->addSelect(['registered' => $this->registeredSubquery()])
            ->orderBy('surname')->orderBy('first_name')
            ->paginate(25)->withQueryString();

        $options = [];
        foreach (['rank', 'directorate', 'command'] as $col) {
            $options[$col] = PersonnelRecord::whereNotNull($col)->distinct()->orderBy($col)->pluck($col);
        }

        return view('admin.personnel.index', [
            'records' => $records,
            'options' => $options,
            'statuses' => PersonnelAdminService::STATUSES,
            'provider' => config('personnel.provider'),
            'filters' => $request->only(['q', 'status', 'rank', 'directorate', 'command', 'registered']),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->audit->log('personnel.exported', actorId: $request->user()->id, resourceType: 'personnel_record',
            metadata: ['filters' => array_filter($request->only(['q', 'status', 'rank', 'directorate', 'command', 'registered']))]);

        $rows = (function () use ($request) {
            $query = $this->filtered($request)->addSelect(['registered' => $this->registeredSubquery()])->orderBy('surname');
            foreach ($query->lazy(500) as $r) {
                yield [...array_map(fn ($c) => $r->{$c}, PersonnelAdminService::COLUMNS), $r->registered ? 'yes' : 'no', $r->source];
            }
        })();

        return CsvExport::download('personnel-'.now()->format('Ymd-His').'.csv',
            [...PersonnelAdminService::COLUMNS, 'registered', 'source'], $rows);
    }

    public function create(): View
    {
        return view('admin.personnel.form', [
            'record' => new PersonnelRecord(['status' => 'active']),
            'account' => null,
            'statuses' => PersonnelAdminService::STATUSES,
            'provider' => config('personnel.provider'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->personnel->rules(), $this->personnel->messages());
        $record = PersonnelRecord::create($data + ['source' => 'admin', 'source_synced_at' => now()]);

        $this->audit->log('personnel.created', actorId: $request->user()->id, resourceType: 'personnel_record',
            resourceId: $record->id, metadata: ['service_number' => $record->service_number, 'status' => $record->status]);

        return redirect()->route('admin.personnel.index', ['q' => $record->service_number])
            ->with('status', "Personnel record for {$record->service_number} created.");
    }

    public function edit(PersonnelRecord $personnel): View
    {
        return view('admin.personnel.form', [
            'record' => $personnel,
            'account' => $this->personnel->accountFor($personnel),
            'statuses' => PersonnelAdminService::STATUSES,
            'provider' => config('personnel.provider'),
        ]);
    }

    public function update(Request $request, PersonnelRecord $personnel): RedirectResponse
    {
        $data = $request->validate($this->personnel->rules($personnel->id), $this->personnel->messages());
        $actor = $request->user();
        $account = $this->personnel->accountFor($personnel);

        if ($account && $data['service_number'] !== $personnel->service_number) {
            throw ValidationException::withMessages([
                'service_number' => 'This Service Number already has a NISconnect account, so it can\'t be changed.',
            ]);
        }
        if ($account && $data['status'] !== 'active' && $account->account_state === 'active'
            && $this->personnel->isProtected($account, $actor)) {
            throw ValidationException::withMessages([
                'status' => $account->id === $actor->id
                    ? 'You can\'t change your own record to a non-active status — that would suspend your own account.'
                    : 'Only a super administrator can change the status of a super administrator\'s record.',
            ]);
        }

        $before = $personnel->only(array_keys($data));
        $suspended = DB::transaction(function () use ($personnel, $data, $actor) {
            $personnel->update($data);

            return $this->personnel->suspendAccountIfInactive($personnel, $actor);
        });

        $changed = array_keys(array_diff_assoc(array_map('strval', $data), array_map('strval', $before)));
        $this->audit->log('personnel.updated', actorId: $actor->id, resourceType: 'personnel_record',
            resourceId: $personnel->id, metadata: ['service_number' => $personnel->service_number, 'changed' => $changed]);

        $message = 'Personnel record updated.';
        if ($suspended) {
            $message .= ' The linked account was suspended and signed out because the status is now '.$personnel->status.'.';
        }

        return redirect()->route('admin.personnel.edit', $personnel)->with('status', $message);
    }

    public function showImport(): View
    {
        return view('admin.personnel.import', [
            'columns' => PersonnelAdminService::COLUMNS,
            'provider' => config('personnel.provider'),
            'result' => session('import_result'),
        ]);
    }

    public function template(): StreamedResponse
    {
        return CsvExport::download('personnel-import-template.csv', PersonnelAdminService::COLUMNS, [
            ['001234', 'Okafor', 'Adaeze', '', 'Superintendent of Immigration', 'ICT/Cyber Security', 'Software & Data',
                'Zone A', 'Service Headquarters', 'Headquarters', 'Applications Unit', 'Service Headquarters, Abuja',
                'a.okafor@immigration.gov.ng', 'active'],
        ]);
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
            'dry_run' => ['nullable', 'boolean'],
        ], [
            'file.required' => 'Choose a CSV file to import.',
            'file.mimes' => 'The file must be a CSV (.csv) file.',
            'file.max' => 'The file is too large. The limit is 5 MB.',
        ]);

        $dryRun = $request->boolean('dry_run');
        $analysis = $this->personnel->analyse($request->file('file')->getRealPath());
        $summary = [
            'dry_run' => $dryRun,
            'file' => $request->file('file')->getClientOriginalName(),
            'create' => $analysis['create'],
            'update' => $analysis['update'],
            'unchanged' => $analysis['unchanged'],
            'errors' => array_slice($analysis['errors'], 0, 200),
            'error_count' => count($analysis['errors']),
            'preview' => array_map(fn ($r) => [
                'line' => $r['line'],
                'service_number' => $r['data']['service_number'],
                'name' => trim(($r['data']['surname'] ?? '').', '.($r['data']['first_name'] ?? ''), ', '),
                'action' => $r['existing'] ? 'update' : 'create',
            ], array_slice($analysis['rows'], 0, 50)),
        ];

        if ($dryRun) {
            return redirect()->route('admin.personnel.import')->with('import_result', $summary)
                ->with('status', 'Dry run complete — nothing was saved.');
        }

        $counts = $this->personnel->apply($analysis, $request->user());
        $summary += $counts;

        $this->audit->log('personnel.imported', actorId: $request->user()->id, resourceType: 'personnel_record',
            metadata: $counts + ['unchanged' => $analysis['unchanged'], 'errors' => count($analysis['errors'])]);

        return redirect()->route('admin.personnel.import')->with('import_result', $summary)
            ->with('status', "Import finished: {$counts['created']} created, {$counts['updated']} updated"
                .($analysis['errors'] ? ', '.count($analysis['errors']).' row(s) skipped' : '').'.');
    }

    /** @return Builder<PersonnelRecord> */
    private function filtered(Request $request): Builder
    {
        $query = PersonnelRecord::query()->select('personnel_records.*');

        if ($q = trim((string) $request->query('q'))) {
            $like = '%'.addcslashes($q, '%_\\').'%';
            $query->where(function ($w) use ($like, $q) {
                $w->where('surname', 'ilike', $like)
                    ->orWhere('first_name', 'ilike', $like)
                    ->orWhere('other_name', 'ilike', $like)
                    ->orWhereRaw("(first_name || ' ' || surname) ilike ?", [$like]);
                if (ctype_digit($q)) {
                    $w->orWhere('service_number', 'like', $q.'%');
                }
            });
        }
        if (in_array($request->query('status'), PersonnelAdminService::STATUSES, true)) {
            $query->where('status', $request->query('status'));
        }
        foreach (['rank', 'directorate', 'command'] as $col) {
            if (filled($request->query($col))) {
                $query->where($col, (string) $request->query($col));
            }
        }
        if ($request->query('registered') === 'yes') {
            $query->whereExists($this->accountExists());
        } elseif ($request->query('registered') === 'no') {
            $query->whereNotExists($this->accountExists());
        }

        return $query;
    }

    private function accountExists(): \Closure
    {
        return fn ($q) => $q->select(DB::raw(1))->from('users')
            ->whereNull('users.deleted_at')
            ->where(fn ($w) => $w->whereColumn('users.personnel_record_id', 'personnel_records.id')
                ->orWhereColumn('users.service_number', 'personnel_records.service_number'));
    }

    private function registeredSubquery(): \Illuminate\Database\Query\Builder
    {
        return DB::query()->selectRaw('count(*) > 0')->from('users')
            ->whereNull('users.deleted_at')
            ->where(fn ($w) => $w->whereColumn('users.personnel_record_id', 'personnel_records.id')
                ->orWhereColumn('users.service_number', 'personnel_records.service_number'));
    }
}
