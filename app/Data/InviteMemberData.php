<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Attributes\Validation\Email;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The payload of an `invite-member` proposal. A model fills it in; validation
 * here is what stops it inventing fields or shapes.
 */
#[TypeScript('InviteMember')]
final class InviteMemberData extends Data
{
    public function __construct(
        #[Required, Email]
        public string $email,
        #[Required, Max(255)]
        public string $role,
    ) {}
}
