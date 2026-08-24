<?php

declare(strict_types=1);

namespace App\Actions;

use App\Ai\ConfirmableActions;
use App\Exceptions\InvalidConfirmToken;
use App\Models\AiConfirmToken;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * The other half of a proposal. Everything a model touched — the action key and
 * the payload — is re-derived and re-checked here, because the proposal was
 * made by something we do not trust and time has passed since.
 *
 * The row is locked for the length of the transaction and `consumed_at` is set
 * inside it, so a double-submit executes the action exactly once.
 */
final readonly class ConsumeConfirmToken
{
    public function __construct(private OrganizationContext $context) {}

    public function handle(string $token, User $user): mixed
    {
        return DB::transaction(function () use ($token, $user): mixed {
            $confirmation = AiConfirmToken::withoutOrganizationScope()
                ->whereKey($token)
                ->lockForUpdate()
                ->first();

            if (! $confirmation instanceof AiConfirmToken) {
                throw InvalidConfirmToken::unknown($token);
            }

            if ($confirmation->consumed_at !== null) {
                throw InvalidConfirmToken::consumed();
            }

            if ($confirmation->expires_at->isPast()) {
                throw InvalidConfirmToken::expired();
            }

            if ($confirmation->user_id !== $user->id) {
                throw InvalidConfirmToken::wrongUser();
            }

            if ($confirmation->organization_id !== $this->context->id()) {
                throw InvalidConfirmToken::wrongOrganization();
            }

            if (! $confirmation->hasValidSignature()) {
                throw InvalidConfirmToken::tampered();
            }

            $action = ConfirmableActions::find($confirmation->action)
                ?? throw InvalidConfirmToken::unmappedAction($confirmation->action);

            // Permissions change between proposing and confirming. The one that
            // counts is the one held now.
            Gate::forUser($user)->authorize($action->ability());

            $confirmation->forceFill(['consumed_at' => now()])->save();

            return $action->confirm($user, $action->dataClass()::from($confirmation->payload));
        });
    }
}
