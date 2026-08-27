<?php

declare(strict_types=1);

namespace App\Resources\Definitions;

use App\Data\AiConfirmTokenData;
use App\Models\AiConfirmToken;
use App\Policies\AiConfirmTokenPolicy;
use App\Resources\ResourceColumn;
use App\Resources\ResourceContract;
use App\Support\ResourceFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class AiConfirmTokenResource implements ResourceContract
{
    public function key(): string
    {
        return 'ai-confirm-tokens';
    }

    public function label(): string
    {
        return 'AI confirmations';
    }

    public function model(): string
    {
        return AiConfirmToken::class;
    }

    public function dataClass(): string
    {
        return AiConfirmTokenData::class;
    }

    public function policy(): string
    {
        return AiConfirmTokenPolicy::class;
    }

    /**
     * A confirmation is answered where it was raised — the assistant panel on
     * the dashboard, not a page of its own.
     */
    public function url(Model $record): string
    {
        return route('dashboard');
    }

    /**
     * @return list<string>
     */
    public function searchable(): array
    {
        return ['summary', 'action'];
    }

    /**
     * A confirmation is answered while it is fresh, so newest first.
     *
     * @return list<string>
     */
    public function sortable(): array
    {
        return ['created_at', 'summary', 'action'];
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
        return [
            new ResourceColumn(key: 'summary', label: __('Summary')),
            new ResourceColumn(key: 'action', label: __('Action')),
            new ResourceColumn(key: 'created_at', label: __('Raised')),
        ];
    }

    public function recordLabel(Model $record): string
    {
        assert($record instanceof AiConfirmToken);

        return $record->summary;
    }

    public function recordDescription(Model $record): string
    {
        assert($record instanceof AiConfirmToken);

        return $record->action;
    }

    /**
     * A confirmation is addressed to one person, so the organization scope the
     * model carries is not enough on its own: the acting user is part of the
     * where clause, matching what AiConfirmTokenPolicy::view allows. No signed
     * in user means no rows.
     *
     * @return Builder<AiConfirmToken>
     */
    public function scopedQuery(): Builder
    {
        return AiConfirmToken::query()->where('user_id', auth()->id());
    }
}
