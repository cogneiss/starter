<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateOrganization;
use App\Actions\SummarizeAiUsage;
use App\Actions\UpdateOrganization;
use App\Http\Requests\CreateOrganizationRequest;
use App\Http\Requests\UpdateOrganizationRequest;
use App\Models\Organization;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final readonly class OrganizationController
{
    public function create(): Response
    {
        return Inertia::render('organization/create');
    }

    public function store(CreateOrganizationRequest $request, #[CurrentUser] User $user, CreateOrganization $action): RedirectResponse
    {
        $action->handle($user, $request->string('name')->value());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Organization created.'),
        ]);

        return to_route('dashboard');
    }

    public function edit(OrganizationContext $context): Response
    {
        $organization = $context->get();
        assert($organization instanceof Organization);

        Gate::authorize('view', $organization);

        return Inertia::render('organization/edit');
    }

    /**
     * What this organization has spent on AI, and on what. Scoped to the bound
     * organization, so nobody reads another organization's bill.
     */
    public function aiUsage(OrganizationContext $context, SummarizeAiUsage $summarize): Response
    {
        $organization = $context->get();
        assert($organization instanceof Organization);

        Gate::authorize('view', $organization);

        return Inertia::render('organization/ai-usage', [
            'usage' => $summarize->handle($organization, now()->subDays(30)),
        ]);
    }

    public function update(UpdateOrganizationRequest $request, OrganizationContext $context, UpdateOrganization $action): RedirectResponse
    {
        $organization = $context->get();
        assert($organization instanceof Organization);

        Gate::authorize('update', $organization);

        $action->handle($organization, $request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Organization updated.'),
        ]);

        return to_route('organization.edit');
    }
}
