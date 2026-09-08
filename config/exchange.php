<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Storage disk
    |--------------------------------------------------------------------------
    | The filesystem disk that holds every exchange folder. One directory per
    | exchange lives here: <root>/<code>/... Nothing under this disk is
    | web-served; downloads always pass through a controller that checks the
    | session. Switch "local" to an S3-compatible disk later with no code
    | change — only this value and the disk config move.
    */
    'disk' => env('EXCHANGE_STORAGE_DISK', 'exchanges'),

    /*
    |--------------------------------------------------------------------------
    | File size limits (bytes)
    |--------------------------------------------------------------------------
    | "default_max_bytes" is the per-file ceiling for a new exchange (spec §15:
    | 100 MB). An administrator may raise an individual exchange's limit up to
    | "max_allowed_bytes", which also bounds what the PHP/web-server config
    | must accept.
    */
    'default_max_bytes' => (int) env('EXCHANGE_DEFAULT_MAX_BYTES', 100 * 1024 * 1024),
    'max_allowed_bytes' => (int) env('EXCHANGE_MAX_ALLOWED_BYTES', 2 * 1024 * 1024 * 1024),

    /*
    |--------------------------------------------------------------------------
    | Lifetime
    |--------------------------------------------------------------------------
    | Normal exchange lifetime in days (spec §15: 14). Adding or replacing a
    | file resets expiration to now + this many days. Downloads, views and
    | logins never extend it.
    */
    'lifetime_days' => (int) env('EXCHANGE_LIFETIME_DAYS', 14),

    /*
    |--------------------------------------------------------------------------
    | Exchange code
    |--------------------------------------------------------------------------
    | Length of an auto-generated exchange code. The alphabet deliberately
    | omits visually ambiguous characters (0/O, 1/I/L).
    */
    'code_length' => (int) env('EXCHANGE_CODE_LENGTH', 8),
    'code_alphabet' => 'ABCDEFGHJKMNPQRSTUVWXYZ23456789',

    /*
    |--------------------------------------------------------------------------
    | Email verification (Send Files to Radcal)
    |--------------------------------------------------------------------------
    */
    'verification' => [
        'ttl_minutes' => (int) env('VERIFICATION_TTL_MINUTES', 15),
        'max_attempts' => (int) env('VERIFICATION_MAX_ATTEMPTS', 5),
        'resend_seconds' => (int) env('VERIFICATION_RESEND_SECONDS', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Warn-before-expiry window (days)
    |--------------------------------------------------------------------------
    | Exchanges expiring within this many days are surfaced by the
    | "exchanges:report-expiring" command (built in Milestone 3).
    */
    'expiring_soon_days' => (int) env('EXCHANGE_EXPIRING_SOON_DAYS', 2),

];
