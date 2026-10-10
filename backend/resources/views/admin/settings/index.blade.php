@extends('admin.layout')
@section('title', 'System settings')
@section('inline-errors', '1')
@section('content')
<x-admin.page-header title="System settings" subtitle="Platform-wide rules. Changes take effect immediately and are recorded in the audit log." />

<div class="grid grid-sidebar">
    <div class="card">
        <div class="card-head"><h2>Settings</h2></div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.settings.update') }}">
                @csrf @method('PUT')
                @foreach($definitions as $key => $def)
                    <div class="field">
                        @if($def['type'] === 'bool')
                            <input type="hidden" name="{{ $key }}" value="0">
                            <label class="check">
                                <input type="checkbox" id="{{ $key }}" name="{{ $key }}" value="1" @checked((string) old($key, $values[$key] ? '1' : '0') === '1')>
                                <span>{{ $def['label'] }}<span class="help" style="display:block">{{ $def['help'] }}</span></span>
                            </label>
                        @elseif($def['type'] === 'int')
                            <label for="{{ $key }}">{{ $def['label'] }}</label>
                            <input type="number" id="{{ $key }}" name="{{ $key }}" value="{{ old($key, $values[$key]) }}"
                                   @isset($def['min']) min="{{ $def['min'] }}" @endisset @isset($def['max']) max="{{ $def['max'] }}" @endisset
                                   step="1" required style="max-width:160px">
                            <div class="help">{{ $def['help'] }}@isset($def['min'], $def['max']) Between {{ $def['min'] }} and {{ $def['max'] }}.@endisset</div>
                        @else
                            <label for="{{ $key }}">{{ $def['label'] }}</label>
                            <input id="{{ $key }}" name="{{ $key }}" value="{{ old($key, $values[$key]) }}">
                            <div class="help">{{ $def['help'] }}</div>
                        @endif
                        @error($key)<div class="error">{{ $message }}</div>@enderror
                    </div>
                @endforeach
                <button class="btn" type="submit"><x-admin.icon name="check" /> Save settings</button>
            </form>
        </div>
    </div>

    <div class="stack">
        <div class="card">
            <div class="card-head"><h2>Environment</h2><span class="muted small">Read-only</span></div>
            <div class="card-body">
                <p class="muted small" style="margin-top:0">Set on the server in <span class="mono">.env</span>. Secrets are never shown here.</p>
                <dl class="dl">
                    @foreach($environment as $row)
                        <dt>{{ $row['label'] }}</dt>
                        <dd>
                            <span class="mono">{{ $row['value'] !== '' ? $row['value'] : '—' }}</span>
                            @if(! empty($row['warn']))<div class="small" style="color:var(--warning);margin-top:2px"><x-admin.icon name="alert" /> {{ $row['warn'] }}</div>@endif
                        </dd>
                    @endforeach
                </dl>
            </div>
        </div>
        @if(auth()->user()->hasPermission('system.view'))
            <a class="btn btn-ghost" href="{{ route('admin.system.index') }}"><x-admin.icon name="activity" /> System health</a>
        @endif
    </div>
</div>
@endsection
