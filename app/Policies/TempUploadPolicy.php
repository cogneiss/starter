<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\TempUpload;
use App\Models\User;
use App\Support\OrganizationContext;

/**
 * An upload belongs to the person who made it and to nobody else, so the same
 * pair of predicates that {@see TempUpload::ownedBy()} puts in the query is
 * repeated here against the record that came back. The query is the control;
 * this is the second lock on the same door.
 */
final readonly class TempUploadPolicy
{
    public function __construct(private OrganizationContext $context) {}

    public function view(User $user, TempUpload $upload): bool
    {
        return $this->context->id() === $upload->organization_id
            && $user->id === $upload->user_id
            && $user->can('imports.view');
    }
}
