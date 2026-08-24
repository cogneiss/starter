<?php

declare(strict_types=1);

namespace Tests\Fixtures\Ai;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;

/**
 * An agent that belongs to no organization, which the metering middleware has
 * to let through untouched rather than guess an owner for.
 */
final class UnscopedFixtureAgent implements Agent
{
    use Promptable;

    public function instructions(): string
    {
        return 'You are a fixture that belongs to no organization.';
    }
}
