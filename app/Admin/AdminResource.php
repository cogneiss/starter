<?php

declare(strict_types=1);

namespace App\Admin;

use App\Data\AdminRowData;
use App\Resources\ResourceColumn;
use App\Resources\ResourceContract;
use App\Support\ResourceFilter;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One admin page, declared as data. Every page in the control plane is the
 * same machinery — the list kit over a cross-organization query — so a page is
 * a configuration of the generic contract rather than a class of its own.
 *
 * These deliberately live outside `app/Resources/Definitions`: the registry
 * discovers that directory and derives an API `read:` ability from every key
 * it finds, and no admin page may ever become readable through a tenant token.
 */
final readonly class AdminResource implements ResourceContract
{
    /**
     * @param  class-string<Model>  $model
     * @param  Closure(): Builder<covariant Model>  $query
     * @param  list<string>  $searchable
     * @param  list<string>  $sortable
     * @param  Closure():list<ResourceFilter>  $filters
     * @param  list<ResourceColumn>  $columns
     */
    public function __construct(
        private string $key,
        private string $label,
        private string $model,
        private Closure $query,
        private array $searchable,
        private array $sortable,
        private Closure $filters,
        private array $columns,
    ) {}

    public function key(): string
    {
        return $this->key;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function model(): string
    {
        return $this->model;
    }

    public function dataClass(): string
    {
        return AdminRowData::class;
    }

    /**
     * Null on purpose: nothing here is reachable without the platform gate,
     * which the admin middleware enforces before any page resolves.
     */
    public function policy(): ?string
    {
        return null;
    }

    public function url(Model $record): string
    {
        return route('admin.pages', ['page' => $this->key]);
    }

    /**
     * @return list<string>
     */
    public function searchable(): array
    {
        return $this->searchable;
    }

    /**
     * @return list<string>
     */
    public function sortable(): array
    {
        return $this->sortable;
    }

    /**
     * @return list<ResourceFilter>
     */
    public function filters(): array
    {
        return ($this->filters)();
    }

    /**
     * @return list<ResourceColumn>
     */
    public function columns(): array
    {
        return $this->columns;
    }

    public function recordLabel(Model $record): string
    {
        return $this->columns[0]->valueFor($record);
    }

    public function recordDescription(Model $record): ?string
    {
        return null;
    }

    /**
     * Cross-organization by design: the caller supplies a query that either
     * has no organization scope to begin with or explicitly reads without it.
     *
     * @return Builder<covariant Model>
     */
    public function scopedQuery(): Builder
    {
        return ($this->query)();
    }
}
