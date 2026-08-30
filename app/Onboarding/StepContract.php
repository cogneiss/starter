<?php

declare(strict_types=1);

namespace App\Onboarding;

use App\Models\Organization;
use App\Models\User;

/**
 * One thing a new organization has to do before the product is useful to it.
 *
 * A step owns the question "is this done?" and answers it from the application's
 * own data, so a step finished through its ordinary route counts immediately and
 * nothing has to ping the checklist. There is no "mark complete" call to forget.
 */
interface StepContract
{
    /**
     * The stable identifier, used in URLs and in the props.
     */
    public function key(): string;

    public function title(): string;

    public function description(): string;

    /**
     * The name of the route that finishes this step.
     */
    public function route(): string;

    /**
     * A required step gates the application; an optional one only appears on the
     * checklist.
     */
    public function isRequired(): bool;

    /**
     * Where the step sits on the checklist. Lower comes first.
     */
    public function order(): int;

    public function isComplete(User $user, Organization $organization): bool;
}
