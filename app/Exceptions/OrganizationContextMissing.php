<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when an organization-scoped query runs with no organization bound and
 * strict scoping is on. Never downgrade this to an unscoped query: that returns
 * every organization's rows to whoever asked.
 */
final class OrganizationContextMissing extends RuntimeException
{
    public static function for(string $model): self
    {
        return new self(
            "No organization is bound to the current context, so [{$model}] cannot be queried safely. ".
            'Bind one with OrganizationContext::runAs(), or set ORGANIZATIONS_STRICT=false while migrating.'
        );
    }
}
