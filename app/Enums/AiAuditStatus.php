<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript('AiAuditStatus')]
enum AiAuditStatus: string
{
    case Ok = 'ok';
    case Blocked = 'blocked';
    case Failed = 'failed';
}
