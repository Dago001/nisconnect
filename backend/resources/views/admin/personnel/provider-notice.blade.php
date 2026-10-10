@php
    $providerLabels = ['demo' => 'Demo data (development only)', 'api' => 'NIS personnel API', 'database' => 'NIS personnel database'];
    // PERSONNEL_DB_TABLE=personnel_records on the app's own database (the Render
    // setup): this list itself decides who may register.
    $usesThisList = $provider === 'database'
        && config('personnel.database.table') === 'personnel_records'
        && config('personnel.database.connection') === config('database.default');
@endphp
<div class="alert alert-info">
    @if($usesThisList)
        <strong>This list controls registration.</strong> Only officers whose Service Number is listed here with status <em>active</em> can create an account. Import or add the authorised officers before telling them to sign up.
    @else
        <strong>Personnel source:</strong> {{ $providerLabels[$provider] ?? ucfirst((string) $provider) }}.
        @if($provider === 'demo')
            Registration currently checks the built-in demo provider. Records you add here are kept in the local mirror and marked as added by an administrator.
        @else
            Registration is verified against the authorised source; records here are the local mirror. Records you add or import are marked as added by an administrator.
        @endif
    @endif
</div>
