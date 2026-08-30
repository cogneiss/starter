<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\OnboardingProgress;
use App\Models\Organization;
use App\Models\User;
use App\Onboarding\Checklist;
use App\Support\OrganizationContext;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * The screen a new owner lands on, and the one decision they can make about it.
 *
 * There is no "start" and no "step 3 of 5". The page renders the same checklist
 * every time and points at the first thing left to do, so leaving halfway
 * through and coming back a day later resumes exactly where it stopped.
 */
final readonly class OnboardingController
{
    public function __construct(
        private OrganizationContext $context,
        private Checklist $checklist,
    ) {}

    public function show(#[CurrentUser] User $user): Response
    {
        return Inertia::render('onboarding/show', [
            'checklist' => $this->checklist->for($user, $this->organization()),
        ]);
    }

    /**
     * Skip onboarding, permanently. Somebody who knows the product does not need
     * to be walked through it, and asking them again tomorrow is nagging.
     */
    public function store(#[CurrentUser] User $user): RedirectResponse
    {
        $progress = $this->checklist->decision($user) ?? new OnboardingProgress;

        $progress->user()->associate($user);
        $progress->skipped_at = now();
        $progress->save();

        return to_route('dashboard');
    }

    private function organization(): Organization
    {
        $organization = $this->context->get();

        throw_unless($organization instanceof Organization, RuntimeException::class, 'Onboarding belongs behind the organization middleware.');

        return $organization;
    }
}
