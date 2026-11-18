<?php

declare(strict_types=1);

namespace Tests\Fixtures\Knowledge;

use App\Data\UserData;
use App\Models\SavedSearch;
use App\Resources\IndexesKnowledge;
use App\Resources\ResourceColumn;
use App\Support\ResourceFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class SavedSearchKnowledgeResource implements IndexesKnowledge
{
    public function key(): string
    {
        return 'saved-searches';
    }

    public function label(): string
    {
        return 'Saved searches';
    }

    public function model(): string
    {
        return SavedSearch::class;
    }

    public function dataClass(): string
    {
        return UserData::class;
    }

    public function policy(): ?string
    {
        return null;
    }

    public function url(Model $record): string
    {
        return route('dashboard');
    }

    /**
     * @return list<string>
     */
    public function searchable(): array
    {
        return ['name'];
    }

    /**
     * @return list<string>
     */
    public function sortable(): array
    {
        return ['name'];
    }

    /**
     * @return list<ResourceFilter>
     */
    public function filters(): array
    {
        return [];
    }

    /**
     * @return list<ResourceColumn>
     */
    public function columns(): array
    {
        return [new ResourceColumn(key: 'name', label: 'Name')];
    }

    public function recordLabel(Model $record): string
    {
        assert($record instanceof SavedSearch);

        return $record->name;
    }

    public function recordDescription(Model $record): ?string
    {
        return null;
    }

    public function knowledgeTitle(Model $record): string
    {
        assert($record instanceof SavedSearch);

        return $record->name;
    }

    public function knowledgeContent(Model $record): string
    {
        assert($record instanceof SavedSearch);

        return 'Saved search '.$record->name;
    }

    /**
     * @return Builder<SavedSearch>
     */
    public function scopedQuery(): Builder
    {
        return SavedSearch::query();
    }
}
