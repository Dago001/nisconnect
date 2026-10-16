@php
    $providerLabels = [
        'demo' => 'Demo data (development only)',
        'roster' => 'This onboarded officer list',
        'api' => 'NIS personnel API',
        'database' => 'NIS personnel database',
    ];
    // Either the roster provider, or the database provider aimed at this app's
    // own personnel_records table: both mean the list below decides who may
    // register, so say so plainly rather than describing a "source".
    $usesThisList = $provider === 'roster'
        || ($provider === 'database'
            && config('personnel.database.table') === 'personnel_records'
            && config('personnel.database.connection') === config('database.default'));
@endphp
@php
    // The demo provider with accept-any lets ANY well-formed Service Number
    // register, so the list below is ignored entirely. That is a wide-open
    // door, not a note about data sources.
    $acceptsAnyone = $provider === 'demo' && config('personnel.demo_accept_any');
@endphp
<div class="alert {{ $acceptsAnyone ? 'alert-danger' : 'alert-info' }}">
    @if($acceptsAnyone)
        <strong>Anyone can register right now.</strong> The demo personnel source is
        accepting every Service Number, so this list is not being checked and officers
        receive invented details. Set <code>PERSONNEL_PROVIDER=roster</code> and remove
        <code>PERSONNEL_DEMO_ACCEPT_ANY</code> to restrict sign-up to the officers below.
    @elseif($usesThisList)
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
