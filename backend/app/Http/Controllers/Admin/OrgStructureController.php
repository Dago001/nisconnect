<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Command;
use App\Models\Department;
use App\Models\Directorate;
use App\Models\Formation;
use App\Models\Organisation;
use App\Models\Unit;
use App\Models\Zone;
use App\Services\Support\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The NIS organisational hierarchy:
 * Organisation > Directorates > Departments; Organisation > Zones > Commands > Formations > Units.
 */
class OrgStructureController extends Controller
{
    /**
     * type => [model, label, singular, parent type|null, parent FK, child type|null, code required+unique]
     */
    private const TYPES = [
        'directorates' => [Directorate::class, 'Directorates', 'directorate', null, 'organisation_id', 'departments', true],
        'departments' => [Department::class, 'Departments', 'department', 'directorates', 'directorate_id', null, false],
        'zones' => [Zone::class, 'Zones', 'zone', null, 'organisation_id', 'commands', true],
        'commands' => [Command::class, 'Commands', 'command', 'zones', 'zone_id', 'formations', false],
        'formations' => [Formation::class, 'Formations', 'formation', 'commands', 'command_id', 'units', false],
        'units' => [Unit::class, 'Units', 'unit', 'formations', 'formation_id', null, false],
    ];

    public const FORMATION_TYPES = ['hq' => 'Headquarters', 'office' => 'Office', 'border' => 'Border post', 'airport' => 'Airport', 'seaport' => 'Seaport'];

    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): View
    {
        $type = $request->query('type', 'directorates');
        abort_unless(isset(self::TYPES[$type]), 404);
        [$model, , , $parentType, $fk, $childType] = self::TYPES[$type];
        $table = (new $model)->getTable();

        $query = $model::query()->select("$table.*");
        if ($childType) {
            $childFk = self::TYPES[$childType][4];
            $query->addSelect(['children_count' => DB::table($childType)->selectRaw('count(*)')->whereColumn("$childType.$childFk", "$table.id")]);
        }
        if ($parentType) {
            $query->addSelect(['parent_name' => DB::table($parentType)->select('name')->whereColumn("$parentType.id", "$table.$fk")]);
        }
        if ($q = trim((string) $request->query('q'))) {
            $like = '%'.addcslashes($q, '%_\\').'%';
            $query->where(fn ($w) => $w->where("$table.name", 'ilike', $like)->orWhere("$table.code", 'ilike', $like));
        }
        $items = $query->orderBy("$table.name")->paginate(25)->withQueryString();

        $parents = $parentType
            ? self::TYPES[$parentType][0]::orderBy('name')->get(['id', 'name', 'code'])
            : collect();

        $counts = [];
        foreach (self::TYPES as $t => $def) {
            $counts[$t] = $def[0]::count();
        }

        return view('admin.org.index', [
            'type' => $type,
            'types' => self::TYPES,
            'def' => self::TYPES[$type],
            'items' => $items,
            'parents' => $parents,
            'counts' => $counts,
            'organisation' => Organisation::first(),
            'formationTypes' => self::FORMATION_TYPES,
        ]);
    }

    public function store(Request $request, string $type): RedirectResponse
    {
        $def = $this->definition($type);
        $data = $this->validated($request, $type);
        $model = $def[0];

        if ($def[3] === null) {
            $data['organisation_id'] = $this->organisation()->id;
        }
        $item = $model::create($data);

        $this->audit->log('org.created', actorId: $request->user()->id, resourceType: $def[2], resourceId: $item->id,
            metadata: ['name' => $item->name, 'code' => $item->code]);

        return redirect()->route('admin.org.index', ['type' => $type])->with('status', ucfirst($def[2])." \"{$item->name}\" added.");
    }

    public function update(Request $request, string $type, string $id): RedirectResponse
    {
        $def = $this->definition($type);
        $item = $this->find($def, $id);
        $data = $this->validated($request, $type, $item);
        $item->update($data);

        $this->audit->log('org.updated', actorId: $request->user()->id, resourceType: $def[2], resourceId: $item->id,
            metadata: ['name' => $item->name, 'changed' => array_keys($item->getChanges())]);

        return redirect()->route('admin.org.index', ['type' => $type])->with('status', ucfirst($def[2])." \"{$item->name}\" updated.");
    }

    public function destroy(Request $request, string $type, string $id): RedirectResponse
    {
        $def = $this->definition($type);
        $item = $this->find($def, $id);

        if ($childType = $def[5]) {
            $children = DB::table($childType)->where(self::TYPES[$childType][4], $item->id)->count();
            if ($children > 0) {
                return redirect()->route('admin.org.index', ['type' => $type])->with('error',
                    "\"{$item->name}\" still has {$children} ".($children === 1 ? self::TYPES[$childType][2] : $childType)
                    .'. Move or delete those first.');
            }
        }

        $item->delete();
        $this->audit->log('org.deleted', actorId: $request->user()->id, resourceType: $def[2], resourceId: $item->id,
            metadata: ['name' => $item->name, 'code' => $item->code]);

        return redirect()->route('admin.org.index', ['type' => $type])->with('status', ucfirst($def[2])." \"{$item->name}\" deleted.");
    }

    /** @return array{0: class-string<Model>, 1: string, 2: string, 3: ?string, 4: string, 5: ?string, 6: bool} */
    private function definition(string $type): array
    {
        abort_unless(isset(self::TYPES[$type]), 404);

        return self::TYPES[$type];
    }

    private function find(array $def, string $id): Model
    {
        abort_unless(preg_match('/^[0-9a-f-]{36}$/i', $id) === 1, 404);

        return $def[0]::findOrFail($id);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, string $type, ?Model $item = null): array
    {
        [, , $singular, $parentType, $fk, , $codeUnique] = self::TYPES[$type];
        $rules = [
            'name' => ['required', 'string', 'max:150'],
            'code' => $codeUnique
                ? ['required', 'string', 'max:30', Rule::unique($type, 'code')->ignore($item?->id)]
                : ['nullable', 'string', 'max:30'],
        ];
        if ($parentType) {
            $rules[$fk] = ['required', 'uuid', Rule::exists($parentType, 'id')];
        }
        if ($type === 'formations') {
            $rules['type'] = ['nullable', Rule::in(array_keys(self::FORMATION_TYPES))];
        }

        $parentLabel = $parentType ? self::TYPES[$parentType][2] : '';

        return $request->validate($rules, [
            'name.required' => "Enter the {$singular} name.",
            'code.required' => "Enter a short code for the {$singular} (e.g. ICT).",
            'code.unique' => "Another {$singular} already uses this code.",
            "$fk.required" => "Choose the {$parentLabel} this {$singular} belongs to.",
            "$fk.uuid" => "Choose the {$parentLabel} this {$singular} belongs to.",
            "$fk.exists" => "The selected {$parentLabel} no longer exists.",
        ]);
    }

    private function organisation(): Organisation
    {
        return Organisation::first()
            ?? Organisation::create(['name' => 'Nigeria Immigration Service', 'code' => 'NIS']);
    }
}
