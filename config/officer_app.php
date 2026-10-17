<?php

return [

    // The officer phone app (/app) is offered on phones only: a phone is a
    // personal, locked device, so keeping copies of files on it for offline
    // use is reasonable - a shared office computer is not. Set to false to
    // open /app in a desktop browser while developing.
    'phones_only' => (bool) env('OFFICER_APP_PHONES_ONLY', true),

    'offline' => [

        // Automatic saving skips any single file bigger than this, and stops
        // once the saved set reaches the total. An officer can still keep a
        // bigger file by hand with "Keep offline".
        'auto_max_file_mb' => (int) env('OFFICER_APP_AUTO_MAX_FILE_MB', 25),
        'auto_total_mb'    => (int) env('OFFICER_APP_AUTO_TOTAL_MB', 300),

        // Saved copies stop opening (and are removed) once the phone has
        // gone this long without checking in with the server - what ends
        // offline access for an officer whose term is over and who can no
        // longer sign in to be told so.
        'expire_days' => (int) env('OFFICER_APP_OFFLINE_EXPIRE_DAYS', 30),
    ],

];
