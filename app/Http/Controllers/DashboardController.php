<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;
use App\Onboarding\Checklist;
use App\Support\OrganizationContext;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * The dashboard, assembled on the server.
 *
 * A widget a person may not see is not in the props at all. Hiding it in the
 * client would still ship the numbers to the browser, where the network tab is
 * one keystroke away, so the ability decides whether the data is gathered rather
 * than whether it is displayed.
 */
final readonly class DashboardController
{
    /**
     * The widgets this page can show, and the ability each one needs.
     *
     * @var array<string, array{string, string}>
     */
    private const array WIDGETS = [
        'members' => ['members.view', 'Members'],
        'invitations' => ['members.invite', 'Pending invitations'],
    ];

    public function __invoke(#[CurrentUser] User $user, Checklist $checklist, OrganizationContext $context): Response
    {
        $organization = $context->get();

        throw_unless($organization instanceof Organization, RuntimeException::class, 'The dashboard belongs behind the organization middleware.');

        $widgets = [];

        foreach (self::WIDGETS as $key => [$ability, $label]) {
            if ($user->can($ability)) {
                $widgets[$key] = ['label' => $label, 'value' => $this->value($key, $organization)];
            }
        }

        return Inertia::render('dashboard', [
            'widgets' => $widgets,
            'checklist' => $checklist->for($user, $organization),
        ]);
    }

    private function value(string $key, Organization $organization): int
    {
        return match ($key) {
            'members' => $organization->memberships()->count(),
            default => OrganizationInvitation::query()->count(),
        };
    }
}
