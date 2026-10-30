<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\LoadBlogPosts;
use Illuminate\Http\Response;

/**
 * The public pages, and only those: nothing behind a sign-in or in the admin
 * area belongs in a sitemap.
 */
final readonly class SitemapController
{
    public function __invoke(LoadBlogPosts $posts): Response
    {
        $all = $posts->handle();

        $pages = [
            ['loc' => route('home'), 'lastmod' => null],
            ['loc' => route('blog.index'), 'lastmod' => $all->first()?->date],
            ['loc' => route('changelog'), 'lastmod' => null],
        ];

        foreach ($all as $post) {
            $pages[] = ['loc' => route('blog.show', $post->slug), 'lastmod' => $post->date];
        }

        // The declaration is prepended here because Blade cannot compile a
        // template that opens with an XML prolog.
        return response(
            '<?xml version="1.0" encoding="UTF-8"?>'."\n".view('sitemap', ['pages' => $pages])->render(),
            headers: ['Content-Type' => 'application/xml'],
        );
    }
}
