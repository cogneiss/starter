<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Public site content
    |--------------------------------------------------------------------------
    |
    | The blog is a folder of Markdown files and the changelog is one file, both
    | in the repository. There is no CMS: a post is a pull request.
    |
    */

    'blog_path' => base_path('content/blog'),

    'changelog_path' => base_path('CHANGELOG.md'),
];
