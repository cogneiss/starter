<?php

declare(strict_types=1);

use App\Support\Scanners\ClamAvScanner;
use App\Support\Scanners\NullScanner;

return [
    /**
     * Which scanner an upload is handed to before it can be promoted. The
     * default accepts everything and says so loudly; point this at `clamav` on
     * anything that takes files from people you do not know.
     */
    'scanner' => env('UPLOAD_SCANNER') ?: 'null',

    'scanners' => [
        'null' => NullScanner::class,
        'clamav' => ClamAvScanner::class,
    ],

    /** How long an unpromoted upload survives before `uploads:prune` removes it. */
    'ttl_hours' => (int) (env('UPLOAD_TTL_HOURS') ?: 24),
];
