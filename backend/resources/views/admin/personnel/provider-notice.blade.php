@php
    $providerLabels = ['demo' => 'Demo data (development only)', 'api' => 'NIS personnel API', 'database' => 'NIS personnel database'];
@endphp
<div class="alert alert-info">
    <strong>Personnel source:</strong> {{ $providerLabels[$provider] ?? ucfirst((string) $provider) }}.
    @if($provider === 'demo')
        Registration currently checks the built-in demo provider. Records you add here are kept in the local mirror and marked as added by an administrator.
    @else
        Registration is verified against the authorised source; records here are the local mirror. Records you add or import are marked as added by an administrator.
    @endif
</div>
