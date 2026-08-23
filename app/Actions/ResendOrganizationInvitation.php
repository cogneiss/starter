<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\OrganizationInvitation;
use App\Notifications\OrganizationInvitationNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class ResendOrganizationInvitation
{
    /**
     * Rotate the token, push the expiry out and mail the invitation again. The
     * old link stops working, which is the point of resending.
     */
    public function handle(OrganizationInvitation $invitation): OrganizationInvitation
    {
        if ($invitation->accepted_at !== null) {
            throw ValidationException::withMessages([
                'email' => __('That invitation has already been accepted.'),
            ]);
        }

        $token = Str::random(40);

        $invitation->forceFill([
            'token' => hash('sha256', $token),
            'expires_at' => now()->addDays(7),
        ])->save();

        Notification::route('mail', $invitation->email)
            ->notify(new OrganizationInvitationNotification($invitation, $token));

        return $invitation;
    }
}
