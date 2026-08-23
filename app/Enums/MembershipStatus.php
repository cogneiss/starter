<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript('MembershipStatus')]
enum MembershipStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
}
