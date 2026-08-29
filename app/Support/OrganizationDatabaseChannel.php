<?php

declare(strict_types=1);

namespace App\Support;

use App\Events\OrganizationNotified;
use Illuminate\Notifications\Channels\DatabaseChannel;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Notification;

/**
 * Laravel's database channel, with the organization the notification was raised
 * in stamped onto the row.
 *
 * Without it every read of the inbox would have to guess which tenant a row
 * belongs to, and a person in two organizations would carry the first one's
 * unread count into the second. The tenant is written once, here, so every read
 * can be a where clause.
 */
final class OrganizationDatabaseChannel extends DatabaseChannel
{
    public function __construct(private readonly OrganizationContext $context) {}

    /**
     * Store the row, then nudge the organization's open tabs.
     */
    public function send(mixed $notifiable, Notification $notification): ?DatabaseNotification
    {
        /** @var DatabaseNotification|null $row */
        $row = parent::send($notifiable, $notification);

        // Outside a tenant — a console notification, say — there is no channel
        // to broadcast on, so the row is written and nothing is announced.
        $organizationId = $this->context->id();

        if (is_string($organizationId)) {
            event(new OrganizationNotified($organizationId));
        }

        return $row;
    }

    /**
     * @return array<array-key, mixed>
     */
    public function buildPayload(mixed $notifiable, Notification $notification): array
    {
        return [
            ...parent::buildPayload($notifiable, $notification),
            'organization_id' => $this->context->id(),
        ];
    }
}
