<?php

declare(strict_types=1);

namespace App\Actions;

use Illuminate\Support\Str;

final readonly class RenderMarkdown
{
    /**
     * Raw HTML is escaped and unsafe link schemes are dropped, so the result is
     * safe to hand to the browser as markup.
     */
    public function handle(string $markdown): string
    {
        return Str::markdown($markdown, [
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]);
    }
}
