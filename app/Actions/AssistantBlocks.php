<?php

declare(strict_types=1);

namespace App\Actions;

use App\Ai\Blocks\BlockCollection;
use App\Ai\Blocks\ConfirmBlock;

/**
 * The confirm blocks hidden in an assistant turn's tool results.
 *
 * Only a confirmation is ever lifted out. Text the model wrote stays text, so a
 * tool result that happens to look like a block cannot dress itself up as one.
 */
final readonly class AssistantBlocks
{
    /**
     * @param  iterable<mixed>  $results  The tool results of one turn, as strings or decoded arrays.
     * @return list<array<mixed>>
     */
    public function handle(iterable $results): array
    {
        $blocks = [];

        foreach ($results as $result) {
            $payload = is_string($result) ? json_decode($result, true) : $result;

            if (! is_array($payload) || ($payload['type'] ?? null) !== 'confirm') {
                continue;
            }

            $block = BlockCollection::block($payload);

            if ($block instanceof ConfirmBlock) {
                $blocks[] = $block->toArray();
            }
        }

        return $blocks;
    }
}
