<?php

declare(strict_types=1);

namespace App\Data;

use Illuminate\Notifications\DatabaseNotification;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript('AppNotification')]
final class NotificationData extends Data
{
    public function __construct(
        public string $id,
        public string $title,
        public ?string $url,
        public string $created_at,
    ) {}

    public static function fromModel(DatabaseNotification $notification): self
    {
        /** @var array<string, mixed> $data */
        $data = $notification->data;

        return new self(
            id: $notification->id,
            title: is_string($data['title'] ?? null) ? $data['title'] : __('Notification'),
            url: is_string($data['url'] ?? null) ? $data['url'] : null,
            created_at: $notification->created_at?->toIso8601String() ?? '',
        );
    }
}
