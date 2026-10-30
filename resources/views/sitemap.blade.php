<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach ($pages as $page)
    <url>
        <loc>{{ $page['loc'] }}</loc>
@if ($page['lastmod'])
        <lastmod>{{ $page['lastmod']->toDateString() }}</lastmod>
@endif
    </url>
@endforeach
</urlset>
