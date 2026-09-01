<?php

declare(strict_types=1);

namespace App\Admin;

use App\Data\AdminRowData;
use App\Resources\ResourceContract;
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
final class AdminResource implements ResourceContract
{
    /**
     * @param  class-string<Model>  $model
     * @param  Closure(): Builder<covariant Model>  $query
     * @param  list<string>  $searchable
     * @param  list<string>  $sortable
     * @param  Closure(): list<\App\Support\ResourceFilter>  $filters
     * @param  list<\App\Resources\ResourceColumn>  $columns
     */
    public function __construct(
        private readonly string $key,
        private readonly string $label,
        private readonly string $model,
        private readonly Closure $query,
        private readonly array $searchable,
        private readonly array $sortable,
        private readonly Closure $filters,
        private readonly array $columns,
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
     * @return list<\App\Support\ResourceFilter>
     */
    public function filters(): array
    {
        return ($this->filters)();
    }

    /**
     * @return list<\App\Resources\ResourceColumn>
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
