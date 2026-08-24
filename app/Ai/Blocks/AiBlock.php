<?php

declare(strict_types=1);

namespace App\Ai\Blocks;

use App\Enums\AiBlockType;
use Illuminate\Contracts\Support\Arrayable;

/**
 * One member of the block union an agent returns.
 *
 * Agents return blocks and never HTML, which is the whole point of the union:
 * rendering markup a model wrote is an XSS hole. Every member carries its own
 * `type`, and the React side switches on it exhaustively.
 *
 * @extends Arrayable<string, mixed>
 */
interface AiBlock extends Arrayable
{
    public AiBlockType $type { get; }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
