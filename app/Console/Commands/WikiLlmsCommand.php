<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\WikiPage;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Writes `llms.txt` (llmstxt.org) from the wiki, so a coding agent can orient in
 * the repository from one file. The wiki is the source of truth and this file is
 * output: `--check` fails when the committed copy differs, which is how it stays
 * from drifting. Links are repo-relative because the file describes the
 * repository, not a public site.
 *
 * `llms-full.txt` is deliberately not produced: the pages are one link away.
 */
#[Description('Write llms.txt from the wiki, or with --check fail when the committed file is out of date')]
#[Signature('wiki:llms {--check : Fail instead of writing when llms.txt differs} {--path= : The wiki directory, defaults to wiki/} {--out= : The file to write, defaults to llms.txt}')]
final class WikiLlmsCommand extends Command
{
    private const string TITLE = 'Starter';

    private const string SUMMARY = 'A Laravel and Inertia React starter kit with an AI layer, an API and platform operations. The wiki below is the reasoning behind the code; read the page for an area before editing it.';

    public function handle(): int
    {
        $root = $this->stringOption('path') ?? base_path('wiki');
        $out = $this->stringOption('out') ?? base_path('llms.txt');

        $pages = WikiPage::in($root);

        if ($pages === []) {
            $this->components->error(sprintf('No wiki pages found in %s.', $root));

            return self::FAILURE;
        }

        $contents = $this->render($pages);

        if (! $this->option('check')) {
            file_put_contents($out, $contents);
            $this->components->info(sprintf('Wrote %s from %d wiki page(s).', $out, count($pages)));

            return self::SUCCESS;
        }

        if (is_file($out) && file_get_contents($out) === $contents) {
            $this->components->info('llms.txt is current.');

            return self::SUCCESS;
        }

        $this->components->error('llms.txt is out of date. Run `php artisan wiki:llms` and commit the result.');

        return self::FAILURE;
    }

    /**
     * @param  array<string, WikiPage>  $pages
     */
    private function render(array $pages): string
    {
        $sections = [];

        foreach ($pages as $slug => $page) {
            $folder = str_contains($slug, '/') ? Str::before($slug, '/') : 'overview';

            $sections[$folder][] = sprintf(
                '- [%s](wiki/%s.md): %s',
                $this->titleOf($page),
                $slug,
                $this->descriptionOf($page),
            );
        }

        ksort($sections);

        $text = '# '.self::TITLE."\n\n> ".self::SUMMARY."\n";

        foreach ($sections as $folder => $lines) {
            $text .= "\n## ".Str::headline($folder)."\n\n".implode("\n", $lines)."\n";
        }

        return $text;
    }

    private function titleOf(WikiPage $page): string
    {
        $title = $page->frontmatter['title'] ?? null;

        return is_string($title) && $title !== '' ? $title : $page->slug;
    }

    /**
     * The first sentence of the first prose paragraph. Pages carry no summary
     * field, and the opening paragraph is where each one already says what it
     * is.
     */
    private function descriptionOf(WikiPage $page): string
    {
        foreach (explode("\n\n", $page->body) as $block) {
            $block = mb_trim($block);

            if ($block === '' || str_contains('#|-*`>< 0123456789', $block[0])) {
                continue;
            }

            $text = (string) preg_replace('/\[\[(?:[^\]|]+\|)?([^\]]+)\]\]/', '$1', (string) preg_replace('/\s+/', ' ', $block));

            return mb_rtrim(Str::limit(Str::before($text.' ', '. '), 200), ' :;,');
        }

        return $this->titleOf($page);
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
