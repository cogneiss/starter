<?php

declare(strict_types=1);

namespace App\Onboarding\Steps;

use App\Models\Organization;
use App\Models\User;
use App\Onboarding\StepContract;

/**
 * Turn on two-factor authentication. Worth asking for, not worth blocking on.
 */
final class EnableTwoFactorStep implements StepContract
{
    public function key(): string
    {
        return 'two-factor';
    }

    public function title(): string
    {
        return 'Turn on two-factor authentication';
    }

    public function description(): string
    {
        return 'Add a second factor to your own sign in.';
    }

    public function route(): string
    {
        return 'two-factor.show';
    }

    public function isRequired(): bool
    {
        return false;
    }

    public function order(): int
    {
        return 30;
    }

    public function isComplete(User $user, Organization $organization): bool
    {
        return $user->two_factor_confirmed_at !== null;
    }
}
