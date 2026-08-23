<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;
use App\Notifications\OrganizationInvitationNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class CreateOrganizationInvitation
{
    public function __construct(private ResendOrganizationInvitation $resend) {}

    /**
     * Invite an email address to the organization. An email that already has a
     * pending invitation is re-sent rather than invited twice, and one that is
     * already a member is refused.
     */
    public function handle(Organization $organization, User $invitedBy, string $email, string $role): OrganizationInvitation
    {
        if ($this->isMember($organization, $email)) {
            throw ValidationException::withMessages([
                'email' => __('That person is already a member of this organization.'),
            ]);
        }

        $pending = OrganizationInvitation::query()
            ->where('email', $email)
            ->whereNull('accepted_at')
            ->first();

        if ($pending instanceof OrganizationInvitation) {
            return $this->resend->handle($pending);
        }

        $token = Str::random(40);

        $invitation = OrganizationInvitation::query()->create([
            'organization_id' => $organization->id,
            'email' => $email,
            'role' => $role,
            'token' => hash('sha256', $token),
            'invited_by_user_id' => $invitedBy->id,
            'expires_at' => now()->addDays(7),
        ]);

        Notification::route('mail', $email)
            ->notify(new OrganizationInvitationNotification($invitation, $token));

        return $invitation;
    }

    private function isMember(Organization $organization, string $email): bool
    {
        return $organization->users()->where('email', $email)->exists();
    }
}
