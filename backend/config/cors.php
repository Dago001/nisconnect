<?php

/*
 * Cross-origin access to the API.
 *
 * The Flutter app is served from a different origin than the API whenever the
 * web client is hosted separately (Netlify, a CDN, or any domain that is not
 * the API's own). Without the browser being told that origin is allowed, every
 * request from it is blocked before it reaches Laravel.
 *
 * Origins are an allow-list from CORS_ALLOWED_ORIGINS, comma-separated:
 *
 *   CORS_ALLOWED_ORIGINS=https://nisconnect.netlify.app,https://app.immigration.gov.ng
 *
 * Left empty, nothing cross-origin is allowed — which is the right default for
 * a single-origin deployment (infrastructure/production serves the web app and
 * the API from one domain through Caddy, so it needs none of this).
 *
 * Deliberately never '*': this API is authenticated, and a wildcard origin
 * would let any site on the internet make credentialed calls on a signed-in
 * officer's behalf.
 */

$origins = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))
)));

return [

    // The admin portal is server-rendered and same-origin, so it is not listed.
    'paths' => ['api/*', 'broadcasting/auth', 'sanctum/csrf-cookie', 'up'],

    'allowed_methods' => ['*'],

    'allowed_origins' => $origins,

    // Netlify deploy previews get a generated subdomain per pull request; set
    // CORS_ALLOWED_ORIGIN_PATTERNS to a regex to admit them without listing
    // each one, e.g. '^https://[a-z0-9-]+--nisconnect\.netlify\.app$'
    'allowed_origins_patterns' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CORS_ALLOWED_ORIGIN_PATTERNS', ''))
    ))),

    'allowed_headers' => ['*'],

    // Lets the client read pagination and rate-limit headers it already uses.
    'exposed_headers' => ['X-RateLimit-Limit', 'X-RateLimit-Remaining', 'Retry-After'],

    'max_age' => 86400,

    // The app authenticates with a bearer token, not cookies, so credentialed
    // requests are not needed. Keeping this false also means a misconfigured
    // origin cannot be used to ride a session.
    'supports_credentials' => false,

];
