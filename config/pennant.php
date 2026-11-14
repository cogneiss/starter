<?php

declare(strict_types=1);

/**
 * Only the values that differ from Pennant's defaults.
 */
return [
    /**
     * Every flag resolves from the feature_overrides table on each request,
     * so the array store is the default: nothing goes stale.
     */
    'default' => env('PENNANT_STORE', 'array'),
];
