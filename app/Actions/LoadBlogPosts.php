<?php

declare(strict_types=1);

namespace App\Actions;

use App\Support\Blog\BlogPost;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use GlobIterator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Symfony\Component\Yaml\Yaml;

final readonly class LoadBlogPosts
{
    /**
     * Published posts, newest first. Drafts show outside production only. A
     * file without front matter, or without a title or date, is skipped rather
     * than breaking the whole blog.
     *
     * @return Collection<int, BlogPost>
     */
    public function handle(): Collection
    {
        $posts = [];

        foreach (new GlobIterator(config()->string('site.blog_path').'/*.md') as $file) {
            $post = $this->parse((string) $file);

            if ($post instanceof BlogPost) {
                $posts[] = $post;
            }
        }

        return collect($posts)
            ->reject(fn (BlogPost $post): bool => $post->draft && app()->isProduction())
            ->sortByDesc(fn (BlogPost $post): int => $post->date->getTimestamp())
            ->values();
    }

    private function parse(string $file): ?BlogPost
    {
        if (preg_match('/\A---\R(.*?)\R---\R?(.*)\z/s', File::get($file), $parts) !== 1) {
            return null;
        }

        $meta = Yaml::parse($parts[1], Yaml::PARSE_DATETIME);

        $date = is_array($meta) ? ($meta['date'] ?? null) : null;

        if (! is_array($meta) || ! is_string($meta['title'] ?? null) || ! $date instanceof DateTimeInterface && ! is_string($date)) {
            return null;
        }

        return new BlogPost(
            slug: pathinfo($file, PATHINFO_FILENAME),
            title: $meta['title'],
            description: is_string($meta['description'] ?? null) ? $meta['description'] : '',
            date: CarbonImmutable::parse($date),
            draft: ($meta['draft'] ?? false) === true,
            author: is_string($meta['author'] ?? null) ? $meta['author'] : null,
            body: mb_trim($parts[2]),
        );
    }
}
