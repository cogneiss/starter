<?php

declare(strict_types=1);

namespace App\Resources;

use App\Support\ResourceFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\LaravelData\Data;

/**
 * One adapter per user-facing model. The convergence point for everything
 * generic in the app.
 *
 * Deliberately omitted until something consumes them: searchQuery() (Scout),
 * scopeFilter()/visibleTo()/find() (search authorization), actions()/
 * actionSchemas() (assistant dispatch). See FEATURES.md "Not included".
 */
interface ResourceContract
{
    /**
     * Stable machine key, plural kebab-case: 'organization-members'.
     */
    public function key(): string;

    /**
     * Human label, plural: 'Organization members'.
     */
    public function label(): string;

    /**
     * @return class-string<Model>
     */
    public function model(): string;

    /**
     * @return class-string<Data>
     */
    public function dataClass(): string;

    /**
     * @return class-string|null Policy class, or null when unauthorized access is impossible.
     */
    public function policy(): ?string;

    /**
     * In-app path a record navigates to. Consumed by links, notifications and breadcrumbs.
     */
    public function url(Model $record): string;

    /**
     * Columns search matches against. A dotted entry ('user.name') matches
     * through a belongsTo relation.
     *
     * @return list<string>
     */
    public function searchable(): array;

    /**
     * Columns a list may be ordered by, most useful first — the first entry is
     * the default order. An allowlist rather than the table's columns, so no
     * request can order by something the screen never shows. A dotted entry
     * ('user.name') orders through a belongsTo relation.
     *
     * @return list<string>
     */
    public function sortable(): array;

    /**
     * The filters a list of this resource offers, declared here rather than on
     * the page: what `f[status]=active` means is a property of the resource, so
     * two screens listing it cannot disagree.
     *
     * @return list<ResourceFilter>
     */
    public function filters(): array;

    /**
     * One-line title for a record in a result list.
     */
    public function recordLabel(Model $record): string;

    /**
     * Optional second line under the label.
     */
    public function recordDescription(Model $record): ?string;

    /**
     * Base query already narrowed to the acting organization. Scoping is
     * query-level: a record outside the organization is not reachable, not
     * merely hidden after the fact.
     *
     * @return Builder<covariant Model>
     */
    public function scopedQuery(): Builder;
}
