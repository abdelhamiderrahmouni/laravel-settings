<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Settings are cached per-key to avoid repeated DB queries. Configure the
    | cache driver, TTL (in seconds), and the prefix used for cache keys.
    |
    */

    'cache' => [
        'driver' => env('SETTINGS_CACHE_DRIVER', 'file'),
        'ttl' => (int) env('SETTINGS_CACHE_TTL', 3600),
        'prefix' => env('SETTINGS_CACHE_PREFIX', 'settings'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Table
    |--------------------------------------------------------------------------
    |
    | The name of the table used to store settings. Change this if your app
    | already uses a table named 'settings' for something else.
    |
    */

    'table' => env('SETTINGS_TABLE', 'settings'),

];
