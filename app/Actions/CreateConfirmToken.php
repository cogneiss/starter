<?php

declare(strict_types=1);

namespace App\Actions;

use App\Ai\ConfirmableActions;
use App\Exceptions\InvalidConfirmToken;
use App\Models\AiConfirmToken;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Turn a proposal into a signed row the person can approve. The action key is
 * looked up in the `ai.actions` allowlist, the payload is validated against the
 * action's Data object, and the caller's permission is checked here as well as
 * at consume time.
 */
final readonly class CreateConfirmToken
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(User $user, string $action, array $payload): AiConfirmToken
    {
        $confirmable = ConfirmableActions::find($action)
            ?? throw InvalidConfirmToken::unmappedAction($action);

        Gate::forUser($user)->authorize($confirmable->ability());

        $data = $confirmable->dataClass()::validateAndCreate($payload);

        $id = (string) Str::uuid();

        return AiConfirmToken::query()->create([
            'id' => $id,
            'user_id' => $user->id,
            'action' => $action,
            'payload' => $payload,
            'signature' => AiConfirmToken::signatureFor($id, $action, $payload),
            'summary' => $confirmable->summary($data),
            'expires_at' => now()->addMinutes(config()->integer('ai.confirm.ttl')),
        ]);
    }
}
