<?php

return [
    'length' => (int) env('OTP_LENGTH', 6),
    'ttl_seconds' => (int) env('OTP_TTL_SECONDS', 300),
    'max_attempts' => (int) env('OTP_MAX_ATTEMPTS', 5),
    'resend_delay_seconds' => (int) env('OTP_RESEND_DELAY_SECONDS', 60),
    'max_resends' => (int) env('OTP_MAX_RESENDS', 3),

    // SMS driver: "log" (dev) or "sms" (real provider adapter).
    'driver' => env('OTP_DRIVER', 'log'),

    /*
    | Return the OTP code in the API response. ONLY honoured outside production,
    | for local development and automated tests. Never true in production.
    */
    'expose_in_response' => (bool) env('OTP_EXPOSE_IN_RESPONSE', false),

    // How long an onboarding verification session lives (seconds).
    'verification_ttl_seconds' => (int) env('OTP_VERIFICATION_TTL_SECONDS', 1800),
];
