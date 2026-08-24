<?php

declare(strict_types=1);

namespace App\Actions;

use App\Ai\Agents\BlockComposer;
use App\Ai\Blocks\AiBlock;
use App\Ai\Blocks\BlockCollection;
use Generator;
use JsonException;
use Laravel\Ai\Streaming\Events\TextDelta;

/**
 * Turns an agent's stream into a stream of blocks.
 *
 * The model's text never reaches the browser. Each complete line is decoded and
 * rebuilt here, which is what keeps the markdown sanitizer and the organization
 * scope on the server side of a response that is otherwise incremental.
 */
final readonly class StreamBlocks
{
    /**
     * @return Generator<int, AiBlock>
     */
    public function handle(BlockComposer $agent, string $prompt): Generator
    {
        $buffer = '';

        foreach ($agent->stream($prompt) as $event) {
            if (! $event instanceof TextDelta) {
                continue;
            }

            $buffer .= $event->delta;

            while (($break = mb_strpos($buffer, "\n")) !== false) {
                $line = mb_substr($buffer, 0, $break);
                $buffer = mb_substr($buffer, $break + 1);

                $block = $this->decode($line);

                if ($block instanceof AiBlock) {
                    yield $block;
                }
            }
        }

        $block = $this->decode($buffer);

        if ($block instanceof AiBlock) {
            yield $block;
        }
    }

    private function decode(string $line): ?AiBlock
    {
        if (mb_trim($line) === '') {
            return null;
        }

        try {
            $payload = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($payload) ? BlockCollection::block($payload) : null;
    }
}
