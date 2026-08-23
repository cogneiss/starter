<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\MembershipStatus;
use App\Models\OrganizationMembership;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript('OrganizationMember')]
final class OrganizationMemberData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public string $email,
        public MembershipStatus $status,
        public ?string $role,
    ) {}

    public static function fromModel(OrganizationMembership $membership): self
    {
        $role = $membership->user->getRoleNames()->first();

        return new self(
            id: $membership->id,
            name: $membership->user->name,
            email: $membership->user->email,
            status: $membership->status,
            role: is_string($role) ? $role : null,
        );
    }
}
