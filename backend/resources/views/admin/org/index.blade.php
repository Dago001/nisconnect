@extends('admin.layout')
@section('title', 'Organisation structure')
@section('inline-errors', '1')
@section('content')
@php
    [$model, $label, $singular, $parentType, $fk, $childType, $codeRequired] = $def;
    $parentSingular = $parentType ? $types[$parentType][2] : null;
@endphp
<x-admin.page-header title="Organisation structure"
    subtitle="{{ $organisation?->name ?? 'Nigeria Immigration Service' }} — directorates and departments, zones, commands, formations and units." />

<nav class="tabs" aria-label="Structure levels">
    @foreach($types as $t => $d)
        <a href="{{ route('admin.org.index', ['type' => $t]) }}" @class(['active' => $t === $type]) @if($t === $type) aria-current="page" @endif>
            {{ $d[1] }} <span class="muted small">{{ number_format($counts[$t]) }}</span></a>
    @endforeach
</nav>

@if($errors->any())
    <div class="alert alert-danger">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
@endif

<div class="grid grid-sidebar">
    <div class="card">
        <form method="GET" action="{{ route('admin.org.index') }}" class="filters" role="search">
            <input type="hidden" name="type" value="{{ $type }}">
            <div class="f" style="max-width:320px"><label for="q">Search {{ strtolower($label) }}</label>
                <input id="q" type="search" name="q" value="{{ request('q') }}" placeholder="Name or code"></div>
            <div><button class="btn btn-ghost" type="submit"><x-admin.icon name="search" /> Search</button></div>
        </form>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Name</th><th>Code</th>@if($parentType)<th>{{ ucfirst($parentSingular) }}</th>@endif
                @if($childType)<th>{{ ucfirst($childType) }}</th>@endif<th></th></tr></thead>
            <tbody>
            @forelse($items as $item)
                <tr>
                    <td><div class="cell-title">{{ $item->name }}</div>
                        @if($type === 'formations' && $item->type)<div class="cell-sub">{{ $formationTypes[$item->type] ?? $item->type }}</div>@endif</td>
                    <td class="mono">{{ $item->code ?? '—' }}</td>
                    @if($parentType)<td>{{ $item->parent_name ?? '—' }}</td>@endif
                    @if($childType)<td>{{ number_format($item->children_count) }}</td>@endif
                    <td class="row-actions">
                        <details>
                            <summary class="btn btn-sm btn-ghost"><x-admin.icon name="edit" /> Edit</summary>
                            <form method="POST" action="{{ route('admin.org.update', [$type, $item->id]) }}" style="margin-top:8px;min-width:240px">
                                @csrf @method('PUT')
                                <div class="field"><label for="name-{{ $item->id }}">Name</label>
                                    <input id="name-{{ $item->id }}" type="text" name="name" value="{{ $item->name }}" required maxlength="150"></div>
                                <div class="field"><label for="code-{{ $item->id }}">Code</label>
                                    <input id="code-{{ $item->id }}" type="text" name="code" value="{{ $item->code }}" maxlength="30" @if($codeRequired) required @endif></div>
                                @if($parentType)
                                    <div class="field"><label for="parent-{{ $item->id }}">{{ ucfirst($parentSingular) }}</label>
                                        <select id="parent-{{ $item->id }}" name="{{ $fk }}" required>
                                            @foreach($parents as $p)<option value="{{ $p->id }}" @selected($p->id === $item->{$fk})>{{ $p->name }}</option>@endforeach
                                        </select></div>
                                @endif
                                @if($type === 'formations')
                                    <div class="field"><label for="ftype-{{ $item->id }}">Type</label>
                                        <select id="ftype-{{ $item->id }}" name="type"><option value="">—</option>
                                            @foreach($formationTypes as $k => $v)<option value="{{ $k }}" @selected($item->type === $k)>{{ $v }}</option>@endforeach
                                        </select></div>
                                @endif
                                <button class="btn btn-sm" type="submit">Save</button>
                            </form>
                        </details>
                        <form method="POST" action="{{ route('admin.org.destroy', [$type, $item->id]) }}"
                            data-confirm="Delete {{ $singular }} “{{ $item->name }}”?"
                            data-confirm-body="This can't be undone. A {{ $singular }} that still has {{ $childType ?? 'children' }} can't be deleted." data-confirm-ok="Delete">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-ghost" type="submit" @if($childType && $item->children_count > 0) title="Has {{ $childType }} — delete those first" @endif>
                                <x-admin.icon name="trash" /> Delete</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5"><x-admin.empty icon="building" title="No {{ strtolower($label) }} yet">
                    @if($parentType && $parents->isEmpty()) Add a {{ $parentSingular }} first, then add {{ strtolower($label) }} to it. @else Use the form to add the first one. @endif
                </x-admin.empty></td></tr>
            @endforelse
            </tbody>
        </table></div>
        {{ $items->links() }}
    </div>

    <form method="POST" action="{{ route('admin.org.store', $type) }}" class="card card-body">
        @csrf
        <h2 style="margin-top:0">Add {{ $singular }}</h2>
        <div class="field"><label for="new-name">Name</label>
            <input id="new-name" type="text" name="name" value="{{ old('name') }}" required maxlength="150">
            @error('name')<div class="error">{{ $message }}</div>@enderror</div>
        <div class="field"><label for="new-code">Code @unless($codeRequired)<span class="muted small">(optional)</span>@endunless</label>
            <input id="new-code" type="text" name="code" value="{{ old('code') }}" maxlength="30" @if($codeRequired) required @endif>
            @if($codeRequired)<div class="help">A short unique code, e.g. ICT.</div>@endif
            @error('code')<div class="error">{{ $message }}</div>@enderror</div>
        @if($parentType)
            <div class="field"><label for="new-parent">{{ ucfirst($parentSingular) }}</label>
                <select id="new-parent" name="{{ $fk }}" required>
                    <option value="">Choose a {{ $parentSingular }}…</option>
                    @foreach($parents as $p)<option value="{{ $p->id }}" @selected(old($fk) === $p->id)>{{ $p->name }}</option>@endforeach
                </select>
                @error($fk)<div class="error">{{ $message }}</div>@enderror</div>
        @endif
        @if($type === 'formations')
            <div class="field"><label for="new-ftype">Type <span class="muted small">(optional)</span></label>
                <select id="new-ftype" name="type"><option value="">—</option>
                    @foreach($formationTypes as $k => $v)<option value="{{ $k }}" @selected(old('type') === $k)>{{ $v }}</option>@endforeach
                </select></div>
        @endif
        <button class="btn" type="submit" @if($parentType && $parents->isEmpty()) disabled @endif><x-admin.icon name="plus" /> Add {{ $singular }}</button>
    </form>
</div>
@endsection
