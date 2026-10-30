<?php

declare(strict_types=1);

it('Blog and changelog pages are accessible', function (string $url, string $title): void {
    visit($url)
        ->wait(1)
        ->assertNoAccessibilityIssues(level: 3)
        ->assertNoJavaScriptErrors()
        ->assertTitle($title);
})->with([
    'blog index' => ['/blog', 'Blog - Laravel'],
    'blog post' => ['/blog/hello-world', 'Hello, world - Laravel'],
    'changelog' => ['/changelog', 'Changelog - Laravel'],
]);
