<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Test mode access password
    |--------------------------------------------------------------------------
    |
    | If password is empty, access gate is disabled.
    |
    */
    'password' => env('TEST_MODE_PASSWORD', ''),

    'session_key' => 'test_mode_access_granted_at',

    'ttl_minutes' => (int) env('TEST_MODE_TTL_MINUTES', 60),

    'telegram_url' => env('TEST_MODE_TELEGRAM_URL', 'https://t.me/id_a_005_a'),

    'telegram_label' => env('TEST_MODE_TELEGRAM_LABEL', '@id_a_005_a'),
];
