<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What a bulk selection of members can be asked to do. Each case names the
 * ability the acting person needs per record, so the bulk path consults the same
 * policy the single-record path does rather than a looser one of its own.
 */
#[TypeScript('BulkMembershipAction')]
enum BulkMembershipAction: string
{
    case Suspend = 'suspend';
    case Reactivate = 'reactivate';
    case Remove = 'remove';

    public function ability(): string
    {
        return match ($this) {
            self::Suspend, self::Reactivate => 'update',
            self::Remove => 'delete',
        };
    }

    /**
     * Whether undoing this needs someone to be re-invited. The screen asks for a
     * confirmation before a destructive action; the server does not care, which
     * is why this says nothing about authorisation.
     */
    public function destructive(): bool
    {
        return $this === self::Remove;
    }
}
