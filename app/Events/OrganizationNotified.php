<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Something was notified inside an organization.
 *
 * The payload is deliberately empty. Everyone in the organization listens on the
 * same channel, so anything put in here would be read by people the notification
 * was not addressed to. The event is a nudge: each client re-reads its own
 * unread count, which the server scopes to that person and that organization.
 */
final class OrganizationNotified implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;

    public function __construct(private readonly string $organizationId) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('organization.'.$this->organizationId)];
    }

    public function broadcastAs(): string
    {
        return 'notification.created';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [];
    }
}
