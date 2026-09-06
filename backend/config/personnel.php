<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Personnel provider
    |--------------------------------------------------------------------------
    | Which authorised NIS personnel source to verify Service Numbers against.
    | Supported: "demo" (development only), "api", "database".
    | The demo provider refuses to run when APP_ENV=production.
    */
    'provider' => env('PERSONNEL_PROVIDER', 'demo'),

    /*
    |--------------------------------------------------------------------------
    | Service Number rules (configurable, not hard-coded)
    |--------------------------------------------------------------------------
    | Digits only. Length may be an exact value, or a min/max range.
    | Leave 'length' null to use the min/max range.
    */
    'service_number' => [
        'length' => env('PERSONNEL_SERVICE_NUMBER_LENGTH') !== null
            ? (int) env('PERSONNEL_SERVICE_NUMBER_LENGTH')
            : null,
        'min' => (int) env('PERSONNEL_SERVICE_NUMBER_MIN', 4),
        'max' => (int) env('PERSONNEL_SERVICE_NUMBER_MAX', 12),
    ],

    /*
    |--------------------------------------------------------------------------
    | Fields returned to the client after verification
    |--------------------------------------------------------------------------
    | Only NIS-approved fields. The officer cannot edit authoritative data.
    */
    'fields' => [
        'service_number', 'surname', 'first_name', 'other_name', 'rank',
        'directorate', 'department', 'zone', 'command', 'formation', 'unit',
        'posting', 'official_email', 'status', 'photo_url',
    ],

    /*
    |--------------------------------------------------------------------------
    | API provider settings
    |--------------------------------------------------------------------------
    */
    'api' => [
        'base_url' => env('PERSONNEL_API_BASE_URL'),
        'key' => env('PERSONNEL_API_KEY'),
        'timeout' => (int) env('PERSONNEL_API_TIMEOUT', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Database provider settings (read-only connection)
    |--------------------------------------------------------------------------
    */
    'database' => [
        'connection' => env('PERSONNEL_DB_CONNECTION', 'nis_personnel'),
        'table' => env('PERSONNEL_DB_TABLE', 'personnel'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Account policy when personnel source marks an officer unauthorised
    |--------------------------------------------------------------------------
    | Applied on re-sync. Supported: "suspend", "disable", "none".
    */
    'unauthorised_policy' => env('PERSONNEL_UNAUTHORISED_POLICY', 'suspend'),
];
