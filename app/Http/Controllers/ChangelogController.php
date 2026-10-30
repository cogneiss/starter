<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\RenderMarkdown;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;
use Inertia\Response;

final readonly class ChangelogController
{
    public function __invoke(RenderMarkdown $markdown): Response
    {
        $path = config()->string('site.changelog_path');

        return Inertia::render('changelog', [
            'html' => $markdown->handle(File::exists($path) ? File::get($path) : ''),
        ]);
    }
}
