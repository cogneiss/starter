<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\OrganizationInvitation;

final readonly class RevokeOrganizationInvitation
{
    public function handle(OrganizationInvitation $invitation): void
    {
        $invitation->delete();
    }
}
