<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use App\Models\WebhookEndpoint;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

final class WebhookEndpointDeactivated extends Notification
{
    use Queueable;

    public function __construct(private readonly WebhookEndpoint $endpoint) {}

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, string>
     */
    public function toDatabase(User $notifiable): array
    {
        return [
            'title' => __('Webhook endpoint deactivated after repeated failures.'),
            'url' => route('webhook.edit'),
        ];
    }
}
