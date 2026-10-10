<?php

return [
    'length' => (int) env('OTP_LENGTH', 6),
    'ttl_seconds' => (int) env('OTP_TTL_SECONDS', 300),
    'max_attempts' => (int) env('OTP_MAX_ATTEMPTS', 5),
    'resend_delay_seconds' => (int) env('OTP_RESEND_DELAY_SECONDS', 60),
    'max_resends' => (int) env('OTP_MAX_RESENDS', 3),

    // Delivery driver: "log" (dev: writes the code to the log), "sms" (generic
    // JSON gateway, services.sms), "termii" (Termii, services.termii) or
    // "none" (send nothing; pair with onboarding_show_code).
    'driver' => env('OTP_DRIVER', 'log'),

    /*
    | Return the OTP code in the API response. ONLY honoured outside production,
    | for local development and automated tests. Never true in production.
    */
    'expose_in_response' => (bool) env('OTP_EXPOSE_IN_RESPONSE', false),

    /*
    | Show the SIGN-UP code on the officer's screen, in any environment, for
    | deployments that have no SMS gateway yet. Only the onboarding code: it
    | proves ownership of the phone the officer typed, while the authorised
    | Service Number list remains the gate. PIN-recovery codes are never shown
    | (that would let anyone reset an officer's PIN); without SMS, an
    | administrator resets PINs from the portal. Turn off once SMS works.
    */
    'onboarding_show_code' => (bool) env('OTP_ONBOARDING_SHOW_CODE', false),

    // How long an onboarding verification session lives (seconds).
    'verification_ttl_seconds' => (int) env('OTP_VERIFICATION_TTL_SECONDS', 1800),
];
