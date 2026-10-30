<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\LoadBlogPosts;
use App\Actions\RenderMarkdown;
use App\Support\Blog\BlogPost;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The public blog. A post is found by comparing the slug against the posts that
 * were loaded, never by building a path from it, so there is no file to reach
 * outside the content folder.
 */
final readonly class BlogController
{
    public function index(LoadBlogPosts $posts): Response
    {
        return Inertia::render('blog/index', [
            'posts' => $posts->handle()->map(fn (BlogPost $post): array => $this->summary($post))->all(),
        ]);
    }

    public function show(string $slug, LoadBlogPosts $posts, RenderMarkdown $markdown): Response
    {
        $post = $posts->handle()->firstWhere('slug', $slug);

        abort_unless($post instanceof BlogPost, 404);

        return Inertia::render('blog/show', [
            'post' => [...$this->summary($post), 'html' => $markdown->handle($post->body)],
        ]);
    }

    /**
     * @return array{slug: string, title: string, description: string, date: string, author: string|null}
     */
    private function summary(BlogPost $post): array
    {
        return [
            'slug' => $post->slug,
            'title' => $post->title,
            'description' => $post->description,
            'date' => $post->date->toDateString(),
            'author' => $post->author,
        ];
    }
}
