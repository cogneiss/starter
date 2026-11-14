<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Ai\Models\Conversation;

/**
 * The only door to saved assistant conversations.
 *
 * The SDK's conversation tables are not tenant-scoped by a global scope, so the
 * organization and the member are a where clause here. A foreign id finds
 * nothing, which the caller turns into a 404 rather than a 403.
 */
final readonly class AssistantConversations
{
    /**
     * @return Builder<Conversation>
     */
    public function handle(User $user, Organization $organization): Builder
    {
        return Conversation::query()
            ->where('organization_id', $organization->id)
            ->where('user_id', $user->id);
    }
}
