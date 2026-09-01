<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\ApiToken;

final readonly class RevokeApiToken
{
    /**
     * Revocation is a stamp, not a delete: the row stays for the usage page and
     * the audit trail until `tokens:prune` retires it.
     */
    public function handle(ApiToken $token): void
    {
        $token->forceFill(['revoked_at' => now()])->save();
    }
}
