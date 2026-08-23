<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\User;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript('Impersonator')]
final class ImpersonatorData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
    ) {}

    public static function fromModel(User $user): self
    {
        return new self(id: $user->id, name: $user->name);
    }
}
