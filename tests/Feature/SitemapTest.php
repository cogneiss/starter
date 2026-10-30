<?php

declare(strict_types=1);

it('Sitemap lists the public pages and the posts as valid xml', function (): void {
    $response = $this->get(route('sitemap'))->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('application/xml');

    $xml = simplexml_load_string((string) $response->getContent());

    expect($xml)->not->toBeFalse();

    preg_match_all('#<loc>(.*?)</loc>#', (string) $response->getContent(), $matches);

    $locations = $matches[1];

    expect($locations)->toContain(route('home'), route('blog.index'), route('changelog'), route('blog.show', 'hello-world'))
        ->and((string) $xml->url[1]->lastmod)->toBe('2026-10-31');
});

it('Sitemap leaves out sign-in, app and admin routes', function (): void {
    $content = (string) $this->get(route('sitemap'))->getContent();

    expect($content)->not->toContain('/dashboard')
        ->not->toContain('/settings')
        ->not->toContain('/admin')
        ->not->toContain('/login');
});

it('Sitemap omits lastmod on the blog index when there are no posts', function (): void {
    config()->set('site.blog_path', storage_path('framework/testing/none'));

    $xml = simplexml_load_string((string) $this->get(route('sitemap'))->getContent());

    expect($xml->url)->toHaveCount(3)
        ->and(isset($xml->url[1]->lastmod))->toBeFalse();
});

it('Sitemap is what robots.txt points to', function (): void {
    $this->get('/robots.txt')
        ->assertOk()
        ->assertSee('Sitemap: '.route('sitemap'));
});
