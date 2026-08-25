<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default Flag Values
    |--------------------------------------------------------------------------
    |
    | The value every organization gets for a flag until a feature_overrides
    | row says otherwise. Keys must match a KnownFeatures case value.
    |
    */

    'defaults' => [
        'ai-briefing-enabled' => env('FEATURE_AI_BRIEFING_ENABLED', false),
        'impersonation-enabled' => env('FEATURE_IMPERSONATION_ENABLED', false),
        'social-login-enabled' => env('FEATURE_SOCIAL_LOGIN_ENABLED', false),
    ],

];
