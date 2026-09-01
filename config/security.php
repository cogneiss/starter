<?php

declare(strict_types=1);

return [
    /**
     * Content-Security-Policy. Shipped default is enforcing; report-only is a
     * rollout aid, not a destination. Extra connect-src origins (an analytics
     * host, an AI endpoint the browser talks to) come from the environment.
     */
    'csp' => [
        'enabled' => env('CSP_ENABLED', true) !== false,
        'report_only' => env('CSP_REPORT_ONLY', false) === true,
        'connect' => array_values(array_filter(array_merge(
            [env('POSTHOG_HOST')],
            explode(',', (string) env('CSP_CONNECT_SRC', '')),
        ))),
    ],

    /**
     * Public-form friction: a honeypot field that must stay empty and a signed
     * timestamp that must be at least min_seconds old and at most max_age
     * seconds old when the form comes back.
     */
    'friction' => [
        'field' => 'website',
        'min_seconds' => 2,
        'max_age_seconds' => 3600,
    ],
];
