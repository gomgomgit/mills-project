<?php

/*
|--------------------------------------------------------------------------
| Filesystems — supports shared_decisions.other_decisions
|--------------------------------------------------------------------------
| "File upload disimpan di Laravel Filesystem local disk (on-premise)."
| FILESYSTEM_DISK controls the default disk used by Storage::disk()
| throughout the app (e.g. weighbridge/grading photo attachments, if any
| screen requires uploads — implemented per-screen in impl-2-screen).
*/
return [

    'default' => env('FILESYSTEM_DISK', 'local'),

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

    ],

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
