<?php

declare(strict_types=1);

namespace App\Ai\Blocks;

use App\Enums\AiBlockType;
use Illuminate\Contracts\Support\Arrayable;
use Spatie\LaravelData\Data;
use Throwable;

/**
 * What an agent's answer is made of.
 *
 * The model names a block; this class decides whether the application has one.
 * A type it does not know, a payload that does not fit, a token belonging to
 * another organization — each produces nothing rather than a guess, because the
 * only alternative to dropping a malformed block is rendering it.
 *
 * @implements Arrayable<int, array<string, mixed>>
 */
final readonly class BlockCollection implements Arrayable
{
    /**
     * @param  list<AiBlock>  $blocks
     */
    private function __construct(public array $blocks) {}

    /**
     * @param  iterable<int, array<string, mixed>>  $payloads
     */
    public static function fromPayloads(iterable $payloads): self
    {
        $blocks = [];

        foreach ($payloads as $payload) {
            $block = self::block($payload);

            if ($block instanceof AiBlock) {
                $blocks[] = $block;
            }
        }

        return new self($blocks);
    }

    /**
     * One block, or null when the model sent something this application cannot
     * build. Every failure mode is the same failure mode on purpose.
     *
     * @param  array<array-key, mixed>  $payload
     */
    public static function block(array $payload): ?AiBlock
    {
        $type = is_string($payload['type'] ?? null)
            ? AiBlockType::tryFrom($payload['type'])
            : null;

        if (! $type instanceof AiBlockType) {
            return null;
        }

        unset($payload['type']);

        try {
            return self::classFor($type)::from($payload);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function toArray(): array
    {
        return array_map(
            fn (AiBlock $block) => $block->toArray(),
            $this->blocks,
        );
    }

    /**
     * @return class-string<AiBlock&Data>
     */
    private static function classFor(AiBlockType $type): string
    {
        $class = match ($type) {
            AiBlockType::Text => TextBlock::class,
            AiBlockType::Markdown => MarkdownBlock::class,
            AiBlockType::Table => TableBlock::class,
            AiBlockType::ListItems => ListBlock::class,
            AiBlockType::Metric => MetricBlock::class,
            AiBlockType::Form => FormBlock::class,
            AiBlockType::Confirm => ConfirmBlock::class,
        };

        return $class;
    }
}
