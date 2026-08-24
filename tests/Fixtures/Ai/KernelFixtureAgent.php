<?php

declare(strict_types=1);

namespace Tests\Fixtures\Ai;

use App\Ai\Agents\Concerns\HasDefaultMiddleware;
use App\Ai\Concerns\OrganizationScopedAgent;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasMiddleware;
use Laravel\Ai\Promptable;

/**
 * A first-party agent shaped exactly like the ones phase 12 adds — final, org
 * scoped, on the default middleware — kept in the test suite so the kernel can
 * be exercised before any product agent exists.
 */
final class KernelFixtureAgent implements Agent, HasMiddleware
{
    use HasDefaultMiddleware;
    use OrganizationScopedAgent;
    use Promptable;

    public function instructions(): string
    {
        return 'You are a fixture for the AI kernel tests.';
    }
}
