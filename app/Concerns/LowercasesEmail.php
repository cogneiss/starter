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
 * Known limit: only an Eloquent write passes through here. A query builder
 * update or a DB::table() write stores what it is given; a check constraint on
 * the column is the upgrade.
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
