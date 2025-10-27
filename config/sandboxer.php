<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Sandboxer Enabled
    |--------------------------------------------------------------------------
    |
    | Enable or disable the entire sandbox functionality.
    |
    */
    'enabled' => env('SANDBOXER_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Session TTL
    |--------------------------------------------------------------------------
    |
    | Time in seconds after which sandbox sessions expire and are cleaned up.
    | Default: 1 hour (3600 seconds)
    |
    */
    'ttl' => env('SANDBOXER_TTL', 3600),

    /*
    |--------------------------------------------------------------------------
    | Demo Credentials
    |--------------------------------------------------------------------------
    |
    | Default demo user credentials for sandbox sessions.
    |
    */
    'demo_credentials' => [
        'email' => env('SANDBOXER_DEMO_EMAIL', 'admin@admin.com'),
        'password' => env('SANDBOXER_DEMO_PASSWORD', 'admin'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Demo Record IDs
    |--------------------------------------------------------------------------
    |
    | Array of record IDs to capture in the initial state snapshot.
    | These records will be available in all sandbox sessions.
    |
    */
    'demo_record_ids' => env('SANDBOXER_DEMO_IDS', [1]),

    /*
    |--------------------------------------------------------------------------
    | Snapshot Tables
    |--------------------------------------------------------------------------
    |
    | Tables to capture in the initial state snapshot.
    |
    */
    'snapshot_tables' => env('SANDBOXER_SNAPSHOT_TABLES', ['users']),

    /*
    |--------------------------------------------------------------------------
    | Excluded Tables
    |--------------------------------------------------------------------------
    |
    | Tables that should NOT be sandboxed (like users, sessions, etc.)
    |
    */
    'excluded_tables' => env('SANDBOXER_EXCLUDED_TABLES', ['users', 'sessions', 'password_reset_tokens', 'migrations']),

    /*
    |--------------------------------------------------------------------------
    | Auto Register Middleware
    |--------------------------------------------------------------------------
    |
    | Automatically register the sandbox middleware globally.
    | If false, you need to manually register it in your kernel.
    |
    */
    'auto_register' => env('SANDBOXER_AUTO_REGISTER', true),

    /*
    |--------------------------------------------------------------------------
    | Cache Settings
    |--------------------------------------------------------------------------
    |
    | Cache configuration for sandbox data.
    |
    */
    'cache' => [
        'enabled' => env('SANDBOXER_CACHE_ENABLED', true),
        'prefix' => env('SANDBOXER_CACHE_PREFIX', 'sandbox'),
        'ttl' => env('SANDBOXER_CACHE_TTL', 3600),
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto-Detection Patterns
    |--------------------------------------------------------------------------
    |
    | Patterns to automatically detect and activate sandbox mode.
    |
    */
    'auto_detection' => [
        'domains' => env('SANDBOXER_AUTO_DOMAINS', 'demo.*.com,sandbox.*.com,try.*.com'),
        'paths' => env('SANDBOXER_AUTO_PATHS', '/demo,/sandbox,/try'),
        'parameters' => ['sandbox' => '1', 'demo' => 'true'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cleanup Settings
    |--------------------------------------------------------------------------
    |
    | Automatic cleanup job configuration.
    |
    */
    'cleanup' => [
        'enabled' => env('SANDBOXER_CLEANUP_ENABLED', true),
        'interval' => env('SANDBOXER_CLEANUP_INTERVAL', 3600), // 1 hour
    ],
];