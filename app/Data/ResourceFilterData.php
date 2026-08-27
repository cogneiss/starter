<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\FilterType;
use App\Support\ResourceFilter;
use Illuminate\Support\Str;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A filter as the table draws it: what it is called, what it offers, how many
 * rows each offer would leave, and what is currently chosen.
 *
 * The screen renders whatever arrives here. It does not know which columns exist
 * or which values are legal, so adding a filter is a change to the resource
 * definition alone.
 *
 * @phpstan-import-type NormalizedFilterValue from ResourceFilter
 */
#[TypeScript('ResourceFilter')]
final class ResourceFilterData extends Data
{
    /**
     * @param  list<ResourceFilterOptionData>  $options
     * @param  NormalizedFilterValue|null  $value
     */
    public function __construct(
        public string $key,
        public string $label,
        public FilterType $type,
        public array $options,
        #[LiteralTypeScriptType('string | boolean | Record<string, string | number> | Array<string> | null')]
        public string|bool|array|null $value,
    ) {}

    /**
     * @param  array<string, int>  $counts
     * @param  NormalizedFilterValue|null  $value
     */
    public static function fromFilter(
        ResourceFilter $filter,
        array $counts,
        string|bool|array|null $value,
    ): self {
        return new self(
            key: $filter->key,
            label: $filter->label,
            type: $filter->type,
            options: self::options($filter, $counts),
            value: $value,
        );
    }

    /**
     * @param  array<string, int>  $counts
     * @return list<ResourceFilterOptionData>
     */
    private static function options(ResourceFilter $filter, array $counts): array
    {
        $values = $filter->type === FilterType::Boolean ? ['1', '0'] : $filter->options;

        return array_map(
            fn (string $value): ResourceFilterOptionData => new ResourceFilterOptionData(
                value: $value,
                label: self::optionLabel($filter, $value),
                count: $counts[$value] ?? 0,
            ),
            $values,
        );
    }

    private static function optionLabel(ResourceFilter $filter, string $value): string
    {
        if ($filter->type !== FilterType::Boolean) {
            return Str::headline($value);
        }

        return $value === '1' ? __('Yes') : __('No');
    }
}
