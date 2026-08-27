<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The shapes a list filter can take. The type decides three things at once: how
 * a value is read out of the query string, how it narrows the query, and which
 * control the table draws for it.
 */
#[TypeScript('FilterType')]
enum FilterType: string
{
    case Select = 'select';
    case MultiSelect = 'multi-select';
    case Boolean = 'boolean';
    case Range = 'range';
    case DateRange = 'date-range';

    /**
     * Whether the type has a countable set of values, so the table can show how
     * many rows each option would leave. A range has no options to count.
     */
    public function countsOptions(): bool
    {
        return match ($this) {
            self::Select, self::MultiSelect, self::Boolean => true,
            self::Range, self::DateRange => false,
        };
    }
}
