<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\GrantAiCredits;
use App\Http\Requests\GrantAiCreditRequest;
use App\Models\Activity;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lets platform staff add AI credit to any organization. Reaching it already
 * took the platform gate, so a non-admin gets a 404 like the rest of `/admin`.
 * The grant carries no actor: a platform admin is not a member holding
 * `ai.grant`, so the action's own permission check does not apply. The audit
 * entry is what records who did it.
 */
final readonly class AdminAiCreditController
{
    public function create(): Response
    {
        return Inertia::render('admin/ai-credit', [
            'organizations' => Organization::query()->orderBy('name')->get(['id', 'name']),
            'maxMicros' => GrantAiCreditRequest::MAX_MICROS,
        ]);
    }

    public function store(GrantAiCreditRequest $request, #[CurrentUser] User $admin, GrantAiCredits $grant): RedirectResponse
    {
        $organization = Organization::query()->findOrFail($request->string('organization_id')->value());

        $entry = $grant->handle($organization, $request->integer('micros'), $request->string('reason')->value());

        Activity::query()->create([
            'organization_id' => $organization->id,
            'log_name' => 'admin',
            'description' => sprintf('granted AI credit to %s', $organization->id),
            'event' => 'granted',
            'subject_type' => $entry->getMorphClass(),
            'subject_id' => $entry->id,
            'causer_type' => $admin->getMorphClass(),
            'causer_id' => $admin->id,
            'properties' => ['micros' => $entry->delta_micros, 'reason' => $entry->reason],
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.credits.granted')]);

        return to_route('admin.pages', ['page' => 'organizations']);
    }
}
