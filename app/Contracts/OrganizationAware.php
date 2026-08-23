<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * Marks a job that touches organization-scoped models. Such a job must also
 * return WithOrganizationContext from its middleware() method — a queue worker
 * has no request, so nothing binds the context otherwise.
 */
interface OrganizationAware
{
    public function organizationId(): ?string;
}
