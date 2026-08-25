<?php

declare(strict_types=1);

namespace App\Ai\Tools\Concerns;

use App\Enums\AiAuditStatus;
use App\Models\AiAuditLog;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

/**
 * A tool never has more authority than the member it runs for.
 *
 * The model decides which tool to call, and the model is downstream of content
 * we do not control, so the tool asks the same policy the controller would have
 * asked. A refusal is recorded before it is rethrown: a tool being told no is
 * the signal that something is trying to read across a boundary.
 */
trait AuthorizesToolCall
{
    /**
     * @throws AuthorizationException
     */
    protected function authorizeFor(User $user, string $ability, mixed $subject): void
    {
        try {
            Gate::forUser($user)->authorize($ability, $subject);
        } catch (AuthorizationException $authorizationException) {
            AiAuditLog::query()->create([
                'user_id' => $user->id,
                'agent' => static::class,
                'status' => AiAuditStatus::Blocked,
                'blocked_reason' => "The tool call was refused: [{$ability}] is not allowed for this member.",
            ]);

            throw $authorizationException;
        }
    }
}
