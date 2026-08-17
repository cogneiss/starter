<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Config;

final class UserMagicLink extends Notification
{
    public function __construct(private readonly string $token) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Your login link'))
            ->line(__('Click the button below to log in to your account.'))
            ->action(__('Log in'), route('magic-link.update', ['token' => $this->token]))
            ->line(__('This link expires in :minutes minutes and can only be used once.', [
                'minutes' => Config::integer('auth.magic_link.expire'),
            ]))
            ->line(__('If you did not request this link, no further action is required.'));
    }
}
