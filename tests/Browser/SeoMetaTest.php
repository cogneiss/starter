<?php

declare(strict_types=1);

it('SeoMeta puts description, canonical and social tags in the head', function (): void {
    $canonical = mb_rtrim(config()->string('app.url'), '/').'/blog/hello-world';

    visit('/blog/hello-world?utm_source=test')
        ->wait(1)
        ->assertScript("document.querySelector('meta[name=description]').content", 'A sample post. Replace it with your own.')
        ->assertScript("document.querySelector('link[rel=canonical]').href", $canonical)
        ->assertScript("document.querySelector('meta[property=\"og:title\"]').content", 'Hello, world')
        ->assertScript("document.querySelector('meta[property=\"og:type\"]').content", 'article')
        ->assertScript("document.querySelector('meta[property=\"og:url\"]').content", $canonical)
        ->assertScript("document.querySelector('meta[name=\"twitter:card\"]').content", 'summary');
});

it('SeoMeta is on the welcome page with a root canonical', function (): void {
    visit('/')
        ->wait(1)
        ->assertScript("document.querySelector('link[rel=canonical]').href", mb_rtrim(config()->string('app.url'), '/').'/')
        ->assertScript("document.querySelector('meta[property=\"og:type\"]').content", 'website');
});
