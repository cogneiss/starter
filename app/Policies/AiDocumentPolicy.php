<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AiDocument;
use App\Models\User;
use App\Support\OrganizationContext;

/**
 * Documents are written by indexing and only ever read, so reading is all there
 * is to authorize. The permission is `ai.view`: a document in the corpus is
 * organization content a member already has, reached through an agent.
 */
final readonly class AiDocumentPolicy
{
    public function __construct(private OrganizationContext $context) {}

    public function viewAny(User $user): bool
    {
        return $this->context->id() !== null && $user->can('ai.view');
    }

    public function view(User $user, AiDocument $document): bool
    {
        return $this->context->id() === $document->organization_id && $user->can('ai.view');
    }
}
