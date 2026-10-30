<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia;

/**
 * A scratch content folder per test, so the sample post never decides a result.
 */
function blogFolder(array $files): string
{
    $folder = storage_path('framework/testing/blog-'.uniqid());

    File::ensureDirectoryExists($folder);

    foreach ($files as $name => $contents) {
        File::put($folder.'/'.$name, $contents);
    }

    config()->set('site.blog_path', $folder);

    return $folder;
}

function blogPost(string $title, string $date, string $extra = '', string $body = 'Body.'): string
{
    return "---\ntitle: {$title}\ndescription: About {$title}\ndate: {$date}\n{$extra}---\n\n{$body}\n";
}

afterEach(function (): void {
    File::deleteDirectories(storage_path('framework/testing'));
});

it('Blog lists posts newest first', function (): void {
    blogFolder([
        'older.md' => blogPost('Older', '2026-01-01'),
        'newer.md' => blogPost('Newer', '2026-06-01', "author: Ada\n"),
    ]);

    $this->get(route('blog.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('blog/index')
            ->where('posts.0.slug', 'newer')
            ->where('posts.0.author', 'Ada')
            ->where('posts.1.slug', 'older'));
});

it('Blog ships a sample post', function (): void {
    $this->get(route('blog.show', 'hello-world'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('blog/show')
            ->where('post.title', 'Hello, world')
            ->where('post.date', '2026-10-31'));
});

it('Blog renders a post as html', function (): void {
    blogFolder(['hello.md' => blogPost('Hello', '2026-01-01', '', '## Heading')]);

    $this->get(route('blog.show', 'hello'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('post.html', "<h2>Heading</h2>\n"));
});

it('Blog hides drafts in production only', function (): void {
    blogFolder([
        'live.md' => blogPost('Live', '2026-01-01'),
        'wip.md' => blogPost('Wip', '2026-02-01', "draft: true\n"),
    ]);

    $this->get(route('blog.show', 'wip'))->assertOk();

    $this->app['env'] = 'production';

    $this->get(route('blog.index'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->has('posts', 1)->where('posts.0.slug', 'live'));
    $this->get(route('blog.show', 'wip'))->assertNotFound();
});

it('Blog skips a file without usable front matter', function (): void {
    blogFolder([
        'plain.md' => 'No front matter here.',
        'untitled.md' => "---\ndate: 2026-01-01\n---\nBody",
        'ok.md' => blogPost('Ok', '2026-01-01'),
    ]);

    $this->get(route('blog.index'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->has('posts', 1));
});

it('Blog answers 404 for an unknown slug', function (): void {
    blogFolder(['ok.md' => blogPost('Ok', '2026-01-01')]);

    $this->get('/blog/nope')->assertNotFound();
});

it('Blog cannot be pointed outside the content folder', function (string $slug): void {
    blogFolder(['ok.md' => blogPost('Ok', '2026-01-01')]);
    File::put(dirname(config()->string('site.blog_path')).'/secret.md', blogPost('Secret', '2026-01-01'));

    $this->get('/blog/'.$slug)->assertNotFound();
})->with([
    'dot segments' => ['..%2Fsecret'],
    'encoded dots' => ['%2e%2e%2fsecret'],
    'plain traversal' => ['../secret'],
    'extension' => ['ok.md'],
]);

it('Blog escapes raw html and drops unsafe links', function (): void {
    blogFolder(['x.md' => blogPost('X', '2026-01-01', '', "<script>alert(1)</script>\n\n[bad](javascript:alert(1))")]);

    $html = $this->get(route('blog.show', 'x'))->viewData('page')['props']['post']['html'];

    expect($html)->toContain('&lt;script&gt;')
        ->not->toContain('<script>')
        ->not->toContain('javascript:');
});

it('Blog shows an empty list when the folder has no posts', function (): void {
    blogFolder([]);

    $this->get(route('blog.index'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->has('posts', 0));
});
