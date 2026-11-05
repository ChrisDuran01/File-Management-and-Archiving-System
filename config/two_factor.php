<?php

return [

    // Every account must set up two-factor sign-in (an authenticator app)
    // before it can use the system - App\Http\Middleware\EnsureTwoFactorEnabled.
    // Keep this true on the server. The test suite turns it off (phpunit.xml)
    // so tests that are not about sign-in don't have to set it up first.
    'required' => (bool) env('TWO_FACTOR_REQUIRED', true),

    // The name the authenticator app shows above the code.
    'issuer' => env('TWO_FACTOR_ISSUER', 'QSU-FMAS'),

    // "Trust this device" lasts this many days.
    'trust_days' => 30,

    // Minutes a correct password waits for its code before signing in again.
    'challenge_minutes' => 10,

];
