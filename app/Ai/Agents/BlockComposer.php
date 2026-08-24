<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Ai\Agents\Concerns\HasDefaultMiddleware;
use App\Ai\Concerns\OrganizationScopedAgent;
use App\Ai\Contracts\OrganizationScoped;
use App\Enums\AiBlockType;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasMiddleware;
use Laravel\Ai\Promptable;

/**
 * Answers in blocks rather than prose.
 *
 * One JSON object per line, so a block can be handed to the browser the moment
 * its line is complete instead of after the whole answer is. The instructions
 * are a request, not a guarantee — App\Ai\Blocks\BlockCollection is what
 * actually decides whether a line becomes a block.
 */
final class BlockComposer implements Agent, HasMiddleware, OrganizationScoped
{
    use HasDefaultMiddleware;
    use OrganizationScopedAgent;
    use Promptable;

    public function instructions(): string
    {
        $types = implode(', ', array_map(
            fn (AiBlockType $type): string => $type->value,
            AiBlockType::cases(),
        ));

        return <<<PROMPT
        You answer with user interface blocks, never with prose and never with HTML.

        Emit one JSON object per line and nothing else — no code fences, no
        commentary, no blank lines. Each object has a "type" of one of: {$types}.

        text:     {"type":"text","text":"..."}
        markdown: {"type":"markdown","markdown":"..."}
        table:    {"type":"table","columns":["..."],"rows":[["..."]]}
        list:     {"type":"list","ordered":false,"items":["..."]}
        metric:   {"type":"metric","label":"...","value":"...","delta":"...","trend":"up|down|flat"}
        form:     {"type":"form","action":"<a confirmable action key>","values":{}}
        confirm:  {"type":"confirm","token":"<a confirmation token id>"}

        Every row of a table has exactly one cell per column. Prefer the most
        specific block that fits: a table for tabular data, a metric for a single
        number, text for a sentence.
        PROMPT;
    }
}
