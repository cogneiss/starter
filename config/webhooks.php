<?php

declare(strict_types=1);

return [
    /**
     * How many delivery attempts a single delivery gets before it is a final
     * failure. Attempt five is the last; there is no sixth.
     */
    'max_attempts' => 5,

    /**
     * Consecutive final failures after which an endpoint is deactivated and
     * the organization's owners are notified.
     */
    'deactivate_after' => 10,

    /**
     * Seconds either side of X-Timestamp inside which a signature is accepted.
     * Outside the window a replayed request is refused even with a valid HMAC.
     */
    'tolerance' => 300,

    /**
     * Seconds to wait for a receiver before an attempt counts as failed.
     */
    'timeout' => 10,
];
