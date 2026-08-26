<?php

declare(strict_types=1);

namespace App\Data;

use App\Support\ResourceQuery;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One page of a list, and the question that produced it.
 *
 * The query travels back with the rows so the table draws its own state from
 * the server rather than remembering it: the sort arrow, the page number and
 * the search box all read from `query`, which means a shared URL renders the
 * same screen for the next person.
 */
#[TypeScript('ResourceList')]
final class ResourceListData extends Data
{
    /**
     * @param  list<Data>  $rows
     */
    public function __construct(
        #[LiteralTypeScriptType('Array<unknown>')]
        public array $rows,
        public int $total,
        public int $pages,
        public ResourceQuery $query,
    ) {}
}
