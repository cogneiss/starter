<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript('AnnouncementLevel')]
enum AnnouncementLevel: string
{
    case Info = 'info';
    case Warning = 'warning';
}
