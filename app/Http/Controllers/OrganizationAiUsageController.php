<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\SummarizeAiUsage;
use App\Models\Organization;
use App\Support\OrganizationContext;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final readonly class OrganizationAiUsageController
{
    /**
     * What this organization has spent on AI, and on what. Scoped to the bound
     * organization, so nobody reads another organization's bill.
     */
    public function index(OrganizationContext $context, SummarizeAiUsage $summarize): Response
    {
        $organization = $context->get();
        assert($organization instanceof Organization);

        Gate::authorize('view', $organization);

        return Inertia::render('organization/ai-usage', [
            'usage' => $summarize->handle($organization, now()->subDays(30)),
        ]);
    }
}
