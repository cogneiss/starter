<?php

declare(strict_types=1);

return [
    /**
     * Days a revoked or expired token, or a usage log row, is kept before the
     * prune commands delete it.
     */
    'retention' => [
        'tokens' => (int) (env('API_TOKEN_RETENTION_DAYS') ?: 30),
        'logs' => (int) (env('API_LOG_RETENTION_DAYS') ?: 90),
    ],

    /**
     * Requests per minute, per plan tier. An organization's tier comes from the
     * `api-rate-tier` Pennant feature; `default` names the tier used when no
     * override is active. Both limits apply — the stricter one wins.
     */
    'rate_tiers' => [
        'default' => 'standard',
        'tiers' => [
            'standard' => [
                'per_token' => 60,
                'per_organization' => 120,
            ],
            'pro' => [
                'per_token' => 600,
                'per_organization' => 1200,
            ],
        ],
    ],
];
