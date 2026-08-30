<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ImportBatch;
use App\Models\User;
use App\Support\OrganizationContext;

/**
 * A batch is one person's run of one file. The scoped query in
 * {@see ImportBatch::ownedBy()} is what makes another organization's id a 404;
 * this repeats the same pair against the record for any route that ever forgets
 * to start there.
 */
final readonly class ImportBatchPolicy
{
    public function __construct(private OrganizationContext $context) {}

    public function view(User $user, ImportBatch $batch): bool
    {
        return $this->context->id() === $batch->organization_id
            && $user->id === $batch->user_id
            && $user->can('imports.view');
    }
}
