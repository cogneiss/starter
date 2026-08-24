<?php

declare(strict_types=1);

namespace App\Ai\Actions;

use App\Actions\CreateOrganizationInvitation;
use App\Ai\Contracts\ConfirmableAction;
use App\Data\InviteMemberData;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;
use App\Support\OrganizationContext;
use RuntimeException;
use Spatie\LaravelData\Data;

/**
 * The agent-facing face of App\Actions\CreateOrganizationInvitation. It adds no
 * behaviour of its own on purpose: an invitation an agent proposed and one a
 * person typed run the same code, under the same policy and the same scope.
 */
final readonly class InviteMember implements ConfirmableAction
{
    public function __construct(
        private CreateOrganizationInvitation $invitations,
        private OrganizationContext $context,
    ) {}

    public function dataClass(): string
    {
        return InviteMemberData::class;
    }

    public function ability(): string
    {
        return 'members.invite';
    }

    public function summary(Data $payload): string
    {
        assert($payload instanceof InviteMemberData);

        return "Invite {$payload->email} as {$payload->role}.";
    }

    public function confirm(User $user, Data $payload): OrganizationInvitation
    {
        assert($payload instanceof InviteMemberData);

        $organization = $this->context->get();

        if (! $organization instanceof Organization) {
            throw new RuntimeException('An invitation needs an organization bound to the context.');
        }

        return $this->invitations->handle($organization, $user, $payload->email, $payload->role);
    }
}
