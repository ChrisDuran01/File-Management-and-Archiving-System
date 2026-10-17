<?php

return [

    // Identifies which wording of the privacy notice a user acknowledged.
    // Change it whenever the notice materially changes and every user will
    // be asked to acknowledge it again on their next page load.
    'version' => env('PRIVACY_NOTICE_VERSION', '2026-09-30'),

    // Shown on the notice. Leave blank until a Data Protection Officer /
    // contact person is actually designated - the page says so honestly.
    'dpo_name'      => env('PRIVACY_DPO_NAME'),
    'contact_email' => env('PRIVACY_CONTACT_EMAIL'),

];
