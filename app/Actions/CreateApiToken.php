<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\NewAccessToken;

final readonly class CreateApiToken
{
    /**
     * The organization is deliberately not a parameter: the token's
     * organization_id comes from the bound OrganizationContext via the
     * BelongsToOrganization creating hook, so a request cannot name one.
     *
     * @param  list<string>  $abilities
     */
    public function handle(User $user, string $name, array $abilities, ?CarbonInterface $expiresAt = null): NewAccessToken
    {
        return DB::transaction(function () use ($user, $name, $abilities, $expiresAt): NewAccessToken {
            $token = $user->createToken($name, $abilities, $expiresAt);

            $token->accessToken->forceFill(['created_by' => $user->id])->save();

            return $token;
        });
    }
}
