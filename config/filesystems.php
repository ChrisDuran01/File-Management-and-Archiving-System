<?php

/*
| Where the files officers upload live - see the 'cloud' disk below.
| Moving from Supabase to Backblaze B2 (October 2026): FILES_CLOUD=supabase
| until every file has been copied (php artisan files:move-to-backblaze),
| then FILES_CLOUD=b2. Switching back is the same one line, and Supabase
| keeps its copy until the move is confirmed.
*/
$supabaseFiles = [
    'driver' => 'supabase',
    'key' => env('SUPABASE_SERVICE_KEY'),
    'bucket' => env('SUPABASE_BUCKET'),
    'endpoint' => env('SUPABASE_URL'),
    'url' => null,
    'public' => true,
    'defaultUrlGeneration' => null,
    'defaultUrlGenerationOptions' => [
        'download' => false,
        'transform' => [],
    ],
    'signedUrlExpires' => 3600,
];

$backblazeFiles = [
    'driver' => 's3',
    'key' => env('FILES_B2_KEY_ID'),
    'secret' => env('FILES_B2_APPLICATION_KEY'),
    'region' => env('FILES_B2_REGION', env('BACKUP_B2_REGION')),
    'bucket' => env('FILES_B2_BUCKET'),
    'endpoint' => env('FILES_B2_ENDPOINT', env('BACKUP_B2_ENDPOINT')),
    'use_path_style_endpoint' => false,
    // Only send/check checksums where S3 requires them - the SDK's newer
    // default adds headers some S3-compatible stores reject.
    'request_checksum_calculation' => 'when_required',
    'response_checksum_validation' => 'when_required',
    // Failures come back as false/null, like the Supabase disk - code such
    // as FolderArchiver relies on a failed upload not throwing midway.
    'throw' => false,
    'report' => true,
];

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

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
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

        /*
        | The single disk every controller/service in this app talks to for
        | user-uploaded documents - application code always calls
        | Storage::disk('cloud'), never a provider. Which provider it is comes
        | from FILES_CLOUD (see the top of this file).
        */
        'cloud' => env('FILES_CLOUD', 'supabase') === 'b2' ? $backblazeFiles : $supabaseFiles,

        // Both, by name, for the move between them (files:move-to-backblaze).
        'supabase' => $supabaseFiles,
        'b2_files' => $backblazeFiles,

        /*
        | Off-site copy of the backup ZIPs, on Backblaze B2 through its S3 API.
        | Kept apart from 'cloud' on purpose: the free Supabase plan refuses
        | any file over 50 MB (a full backup is bigger), and a backup in the
        | same account as the files it protects is lost along with them.
        | Use a B2 application key limited to this one bucket.
        */
        'backups' => [
            'driver' => 's3',
            'key' => env('BACKUP_B2_KEY_ID'),
            'secret' => env('BACKUP_B2_APPLICATION_KEY'),
            'region' => env('BACKUP_B2_REGION'),
            'bucket' => env('BACKUP_B2_BUCKET'),
            'endpoint' => env('BACKUP_B2_ENDPOINT'),
            'use_path_style_endpoint' => false,
            // Only send/check checksums where S3 requires them - the SDK's
            // newer default adds headers some S3-compatible stores reject.
            'request_checksum_calculation' => 'when_required',
            'response_checksum_validation' => 'when_required',
            'throw' => true,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
