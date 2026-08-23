<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\OrganizationInvitation;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript('OrganizationInvitation')]
final class OrganizationInvitationData extends Data
{
    public function __construct(
        public string $id,
        public string $email,
        public string $role,
        public string $expires_at,
    ) {}

    public static function fromModel(OrganizationInvitation $invitation): self
    {
        return new self(
            id: $invitation->id,
            email: $invitation->email,
            role: $invitation->role,
            expires_at: $invitation->expires_at->toDateString(),
        );
    }
}
