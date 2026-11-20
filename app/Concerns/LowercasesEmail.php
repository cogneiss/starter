<?php

declare(strict_types=1);

namespace App\Concerns;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Stores the email column in lower case, so one address is one row on every
 * engine. PostgreSQL and SQLite compare text by case; MySQL's default collation
 * does not.
 *
 * Only an Eloquent write passes through here. On PostgreSQL and MySQL a check
 * constraint, <table>_email_lowercase, refuses a capital from a query builder
 * update or a DB::table() write.
 *
 * Known limit: SQLite has no such constraint, because it cannot add one to an
 * existing table, and stores what a raw write gives it. Creating the table
 * there with raw SQL that includes the check is the upgrade.
 *
 * @phpstan-require-extends Model
 */
trait LowercasesEmail
{
    /**
     * @return Attribute<string, string>
     */
    protected function email(): Attribute
    {
        return Attribute::make(set: fn (string $value): string => Str::lower($value));
    }
}
