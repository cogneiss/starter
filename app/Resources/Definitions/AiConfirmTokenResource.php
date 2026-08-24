<?php

declare(strict_types=1);

namespace App\Resources\Definitions;

use App\Data\AiConfirmTokenData;
use App\Models\AiConfirmToken;
use App\Policies\AiConfirmTokenPolicy;
use App\Resources\ResourceContract;
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

    public function policy(): ?string
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
}
