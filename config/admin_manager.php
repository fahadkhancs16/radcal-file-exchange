<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Secret path
    |--------------------------------------------------------------------------
    | The URL segment this page lives at, e.g. https://share.radcal.com/<path>.
    | Not linked from anywhere in the app. Treat it like a password: pick a
    | long, random, private value per deployment and never share it publicly.
    */
    'path' => env('ADMIN_MANAGER_PATH', 'staff-access'),

    /*
    |--------------------------------------------------------------------------
    | Shared password
    |--------------------------------------------------------------------------
    | A single shared secret (not tied to any one admin account) that unlocks
    | this page for the browser session. Deliberately independent of /admin —
    | this is the recovery path if every admin password is lost. Compared with
    | hash_equals(), so it can be a plain string here just like the database
    | password already is. Leave blank to disable the page entirely (fails
    | closed, not open).
    */
    'password' => env('ADMIN_MANAGER_PASSWORD'),

    /*
    |--------------------------------------------------------------------------
    | Rate limiting
    |--------------------------------------------------------------------------
    */
    'max_attempts' => (int) env('ADMIN_MANAGER_MAX_ATTEMPTS', 5),
    'decay_minutes' => (int) env('ADMIN_MANAGER_DECAY_MINUTES', 15),

];
