<?php

return [

    // Requests per minute from one IP address to the pages anyone can open
    // without signing in (AppServiceProvider). Generous on purpose: once the
    // system is online, a whole campus can reach it through one Wi-Fi IP.
    // This stops one script or one person holding F5 from keeping the server
    // busy; it is not DDoS protection, which needs Cloudflare in front.

    // The student page, announcement and activity pages, sign-in, privacy
    // notice, sitemap and robots.txt.
    'public_pages' => (int) env('THROTTLE_PUBLIC_PAGES', 300),

    // Announcement photos, the share card and the tab icon. A page loads
    // several at once (the browser keeps them for a day after that).
    'public_images' => (int) env('THROTTLE_PUBLIC_IMAGES', 1200),

    // Announcement attachments and public document previews: whole files,
    // the heaviest thing a visitor can ask for.
    'public_downloads' => (int) env('THROTTLE_PUBLIC_DOWNLOADS', 60),

];
