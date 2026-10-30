<?php

declare(strict_types=1);

namespace App\Support\Blog;

use Carbon\CarbonImmutable;

final readonly class BlogPost
{
    public function __construct(
        public string $slug,
        public string $title,
        public string $description,
        public CarbonImmutable $date,
        public bool $draft,
        public ?string $author,
        public string $body,
    ) {}
}
