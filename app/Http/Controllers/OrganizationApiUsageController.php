<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\SummarizeApiUsage;
use App\Models\Organization;
use App\Support\OrganizationContext;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final readonly class OrganizationApiUsageController
{
    /**
     * What the read API has served this organization. Scoped to the bound
     * organization, so nobody reads another organization's traffic.
     */
    public function index(OrganizationContext $context, SummarizeApiUsage $summarize): Response
    {
        $organization = $context->get();
        assert($organization instanceof Organization);

        Gate::authorize('view', $organization);

        return Inertia::render('organization/api-usage', [
            'usage' => $summarize->handle($organization, now()->subDays(30)),
        ]);
    }
}
