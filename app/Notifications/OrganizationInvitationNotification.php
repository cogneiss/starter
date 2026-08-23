<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\OrganizationInvitation;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class OrganizationInvitationNotification extends Notification
{
    public function __construct(
        private readonly OrganizationInvitation $invitation,
        private readonly string $token,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $organization = $this->invitation->organization;

        return (new MailMessage)
            ->subject(__('You have been invited to :organization', ['organization' => $organization->name]))
            ->line(__('You have been invited to join :organization as a :role.', [
                'organization' => $organization->name,
                'role' => $this->invitation->role,
            ]))
            ->action(__('Accept invitation'), route('organization-invitation-acceptance.show', ['token' => $this->token]))
            ->line(__('This invitation expires on :date.', [
                'date' => $this->invitation->expires_at->toFormattedDateString(),
            ]))
            ->line(__('If you were not expecting this invitation, you can ignore this email.'));
    }
}
