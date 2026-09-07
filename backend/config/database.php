<?php

/*
|--------------------------------------------------------------------------
| Database Connections — db-client (shared-modules)
|--------------------------------------------------------------------------
| tech_stack: database=PostgreSQL (arch-spec v2, 2026-09-07 — was MySQL),
| ORM=Eloquent, migration=Laravel Migrations. All Eloquent models
| (App\Models\*) use the default connection below. `sqlite` (in-memory) is
| provided for the Pest/PHPUnit test suite — see phpunit.xml, which
| overrides DB_CONNECTION=sqlite / DB_DATABASE=:memory: for the `testing`
| environment so tests never touch the real PostgreSQL DB.
*/
return [

    'default' => env('DB_CONNECTION', 'pgsql'),

    'connections' => [

        'sqlite' => [
            'driver' => 'sqlite',
            'url' => env('DB_URL'),
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
        ],

        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'mill_smart_log'),
            'username' => env('DB_USERNAME', 'postgres'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ],

    ],

    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],

];
