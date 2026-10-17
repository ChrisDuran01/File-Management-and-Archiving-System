<?php

return [

    // VAPID identifies this server to the browsers' push services. Generate
    // the pair once with `php artisan webpush:vapid` and keep the private
    // key secret - anyone holding it can send pushes as this site.
    'vapid' => [
        'public_key'  => env('WEBPUSH_VAPID_PUBLIC_KEY'),
        'private_key' => env('WEBPUSH_VAPID_PRIVATE_KEY'),
        // Contact the push services can use if we misbehave (mailto: or https:).
        'subject'     => env('WEBPUSH_VAPID_SUBJECT', 'mailto:' . env('MAIL_FROM_ADDRESS', 'admin@example.com')),
    ],

    // Only these push services are accepted as subscription endpoints. The
    // subscribe route is public, so without this anyone could register an
    // arbitrary URL and make the server POST to it (SSRF).
    'allowed_endpoint_hosts' => [
        'fcm.googleapis.com',               // Chrome, Edge, Opera, Brave, Android
        'updates.push.services.mozilla.com', // Firefox
        'push.services.mozilla.com',
        '.push.apple.com',                   // Safari / iOS web apps (suffix match)
        '.notify.windows.com',               // legacy Edge / Windows
    ],

];
