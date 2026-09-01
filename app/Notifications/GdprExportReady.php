<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

final class GdprExportReady extends Notification
{
    public function __construct(private readonly string $file) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * The signed link expires after a day; the file name alone is useless
     * without both the signature and the requester's own session.
     *
     * @return array<string, string>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => __('Your personal-data export is ready to download.'),
            'url' => URL::temporarySignedRoute('gdpr-export.download', now()->addDay(), ['file' => $this->file]),
        ];
    }
}
