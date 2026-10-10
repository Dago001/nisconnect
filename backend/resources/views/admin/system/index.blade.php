@extends('admin.layout')
@section('title', 'System health')
@section('content')
@php
    $tones = ['ok' => 'success', 'warning' => 'warning', 'fail' => 'danger'];
    $labels = ['ok' => 'OK', 'warning' => 'Warning', 'fail' => 'Fail'];
    $failing = collect($checks)->where('status', 'fail')->count();
    $warnings = collect($checks)->where('status', 'warning')->count();
    $passed = collect($checklist)->where('pass', true)->count();
@endphp
<x-admin.page-header title="System health" subtitle="Live checks of the services NISconnect depends on. Refresh the page to run them again.">
    <a class="btn btn-ghost" href="{{ route('admin.system.index') }}"><x-admin.icon name="activity" /> Run checks again</a>
</x-admin.page-header>

@if($failing)
    <div class="alert alert-danger">{{ $failing }} {{ \Illuminate\Support\Str::plural('check', $failing) }} failing. Officers may be affected.</div>
@elseif($warnings)
    <div class="alert alert-warning">All services are reachable, with {{ $warnings }} {{ \Illuminate\Support\Str::plural('warning', $warnings) }} to look at.</div>
@else
    <div class="alert alert-success">All systems are working normally.</div>
@endif

<div class="grid grid-sidebar">
    <div class="stack">
        <div class="card">
            <div class="card-head"><h2>Health checks</h2></div>
            <div class="table-wrap"><table class="table">
                <thead><tr><th>Check</th><th>Status</th><th>Details</th></tr></thead>
                <tbody>
                @foreach($checks as $c)
                    <tr>
                        <td class="cell-title">{{ $c['name'] }}</td>
                        <td><x-admin.badge :value="$c['status']" :tone="$tones[$c['status']] ?? ''">{{ $labels[$c['status']] ?? $c['status'] }}</x-admin.badge></td>
                        <td class="small">{{ $c['detail'] }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        </div>

        <div class="card">
            <div class="card-head"><h2>Production checklist</h2><span class="muted small">{{ $passed }} of {{ count($checklist) }} passed</span></div>
            <div class="table-wrap"><table class="table">
                <tbody>
                @foreach($checklist as $item)
                    <tr>
                        <td style="width:90px">
                            @if($item['pass'])
                                <x-admin.badge value="pass" tone="success"><x-admin.icon name="check" /> Pass</x-admin.badge>
                            @else
                                <x-admin.badge value="fail" tone="danger"><x-admin.icon name="x" /> Fail</x-admin.badge>
                            @endif
                        </td>
                        <td>
                            <div class="cell-title">{{ $item['label'] }}</div>
                            @unless($item['pass'])<div class="cell-sub">{{ $item['help'] }}</div>@endunless
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        </div>
    </div>

    <div class="stack">
        <div class="card card-body">
            <h2>Versions</h2>
            <dl class="dl">
                @foreach($versions as $label => $value)
                    <dt>{{ $label }}</dt><dd class="mono">{{ $value }}</dd>
                @endforeach
            </dl>
        </div>
        @if(auth()->user()->hasPermission('settings.manage'))
            <a class="btn btn-ghost" href="{{ route('admin.settings.index') }}"><x-admin.icon name="cog" /> System settings</a>
        @endif
    </div>
</div>
@endsection
