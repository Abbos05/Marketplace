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

    'session_key' => 'test_mode_access_granted',
];
