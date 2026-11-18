<?php

declare(strict_types=1);

namespace App\Resources;

use Illuminate\Database\Eloquent\Model;

/**
 * An optional capability of a resource adapter: its records join the retrieval
 * corpus the assistant searches. It is a separate interface rather than a
 * seventh method on {@see ResourceContract}, so a resource opts in by
 * implementing it and every other adapter stays as it is.
 *
 * Opt in only for content an organization wrote to be found, never for rows
 * that hold personal data: whatever is returned here is embedded and sent to
 * the provider.
 */
interface IndexesKnowledge extends ResourceContract
{
    public function knowledgeTitle(Model $record): string;

    public function knowledgeContent(Model $record): string;
}
