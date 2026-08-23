<?php

declare(strict_types=1);

namespace Tests\Fixtures\Resources\Valid;

/**
 * Sits in the discovery directory without implementing the contract, so the
 * registry has something to filter out.
 */
final class NotAResource
{
    public function key(): string
    {
        return 'not-a-resource';
    }
}
