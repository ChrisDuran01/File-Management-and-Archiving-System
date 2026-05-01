<?php

return [
    'credentials' => [
        'file' => env('FIREBASE_CREDENTIALS'),
    ],

    'storage' => [
        'default_bucket' => env('FIREBASE_STORAGE_BUCKET'),
    ],
];