<?php

return [

    // How the public student page names itself to search engines and in
    // link previews (Messenger, Facebook): page titles, og:site_name and the
    // schema.org Organization.
    'site_name' => 'QSU Student Government',
    'campus' => 'Diffun Campus',
    'description' => 'Announcements, public documents and officers of the QSU Diffun Student Government.',
    'email' => 'sg@qsu.edu.ph',

    // Google Search Console's "HTML tag" verification code - only the part
    // inside content="...". Leave empty when verifying the domain by DNS
    // instead (Search Console's "Domain" property), which needs no code.
    'google_site_verification' => env('GOOGLE_SITE_VERIFICATION'),

];
