<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\AnnouncementLevel;
use App\Models\Announcement;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript('AnnouncementBanner')]
final class AnnouncementData extends Data
{
    public function __construct(
        public string $id,
        public string $title,
        public string $body,
        public AnnouncementLevel $level,
    ) {}

    public static function fromModel(Announcement $announcement): self
    {
        return new self(
            id: $announcement->id,
            title: $announcement->title,
            body: $announcement->body,
            level: $announcement->level,
        );
    }
}
