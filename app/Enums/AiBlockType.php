<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The complete set of shapes an agent may return. A model that names anything
 * else is producing a block this application does not have, and the block is
 * dropped rather than guessed at.
 */
#[TypeScript('AiBlockType')]
enum AiBlockType: string
{
    case Text = 'text';
    case Markdown = 'markdown';
    case Table = 'table';
    case ListItems = 'list';
    case Metric = 'metric';
    case Form = 'form';
    case Confirm = 'confirm';
}
