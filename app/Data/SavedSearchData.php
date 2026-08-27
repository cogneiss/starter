<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\SavedSearch;
use App\Resources\ResourceContract;
use App\Support\ResourceQuery;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One kept view of a list, as the menu draws it.
 *
 * The stored query never reaches the screen as it was written down. It goes
 * back through `ResourceQuery` first, so what the menu offers is a view the
 * resource still supports rather than whatever was true the day it was saved.
 */
#[TypeScript('SavedSearch')]
final class SavedSearchData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public bool $isDefault,
        public ResourceQuery $query,
    ) {}

    public static function fromModel(SavedSearch $search, ResourceContract $resource): self
    {
        return new self(
            id: $search->id,
            name: $search->name,
            isDefault: $search->is_default,
            query: ResourceQuery::fromParameters($search->query, $resource),
        );
    }
}
