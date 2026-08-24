<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\GitLog;
use App\Support\WikiPage;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * The rot lint for `wiki/`. Documentation that is not gated drifts, and a wiki
 * an agent cannot trust is worse than no wiki: it reads confidently and acts on
 * what it read.
 *
 * Every rule fails the build, and every failure names the page, the thing that
 * outran it, and the way out. The rules themselves are written down for humans
 * in `wiki/_meta/lint.md`.
 */
#[Description('Fail when a wiki page has rotted: dead code refs, dangling links or a stale page')]
#[Signature('wiki:lint {--path= : The wiki directory to lint, defaults to wiki/}')]
final class WikiLintCommand extends Command
{
    /**
     * @var list<string>
     */
    private const array REQUIRED_KEYS = ['title', 'status', 'supersedes', 'code_refs', 'updated'];

    private const string FIX = 'Run /document to reread the code and rewrite the page. Bumping updated: on its own clears this gate and hides the drift.';

    public function handle(): int
    {
        $root = $this->pathOption() ?? base_path('wiki');

        $pages = WikiPage::in($root);

        if ($pages === []) {
            $this->components->error(sprintf('No wiki pages found in %s.', $root));

            return self::FAILURE;
        }

        $failures = [];

        foreach ($pages as $page) {
            $missing = $page->missingKeys(self::REQUIRED_KEYS);

            if ($missing !== []) {
                $failures[] = sprintf(
                    '%s is missing frontmatter: %s. Every page needs all of: %s.',
                    $page->slug,
                    implode(', ', $missing),
                    implode(', ', self::REQUIRED_KEYS),
                );

                continue;
            }

            $failures = [
                ...$failures,
                ...$this->codeRefsResolve($page),
                ...$this->linksResolve($page, $pages),
                ...$this->supersededLinksGoThroughReplacement($page, $pages),
                ...$this->pageOutrunByItsCode($page),
            ];
        }

        return $this->render($pages, $failures);
    }

    /**
     * Rule 1. A code_ref must be a file that exists. Directories are rejected on
     * purpose: a page anchored to a directory goes stale on every unrelated
     * commit inside it, and a gate that is red for unrelated reasons gets
     * switched off.
     *
     * @return list<string>
     */
    private function codeRefsResolve(WikiPage $page): array
    {
        $failures = [];

        foreach ($page->codeRefs() as $ref) {
            $path = base_path($ref);

            if (is_dir($path)) {
                $failures[] = sprintf(
                    '%s lists the directory %s in code_refs. Name the files the page actually explains instead.',
                    $page->slug,
                    $ref,
                );

                continue;
            }

            if (! is_file($path)) {
                $failures[] = sprintf(
                    '%s references %s, which does not exist. It moved or was deleted. %s',
                    $page->slug,
                    $ref,
                    self::FIX,
                );
            }
        }

        return $failures;
    }

    /**
     * Rule 2.
     *
     * @param  array<string, WikiPage>  $pages
     * @return list<string>
     */
    private function linksResolve(WikiPage $page, array $pages): array
    {
        return array_values(array_map(
            fn (string $link): string => sprintf(
                '%s links to [[%s]], which is not a page. Check the slug, or write the page.',
                $page->slug,
                $link,
            ),
            array_filter(
                $page->links(),
                fn (string $link): bool => ! array_key_exists($link, $pages),
            ),
        ));
    }

    /**
     * Rule 3. Superseded pages keep their file, so a link to one is only honest
     * when the reader is also handed the page that replaced it.
     *
     * @param  array<string, WikiPage>  $pages
     * @return list<string>
     */
    private function supersededLinksGoThroughReplacement(WikiPage $page, array $pages): array
    {
        $failures = [];

        foreach ($page->links() as $link) {
            $target = $pages[$link] ?? null;

            if (! $target instanceof WikiPage || ! $target->superseded()) {
                continue;
            }

            $replacements = array_keys(array_filter(
                $pages,
                fn (WikiPage $candidate): bool => in_array($link, $candidate->supersedes(), true),
            ));

            if ($replacements === []) {
                $failures[] = sprintf(
                    '%s links to the superseded page [[%s]], and no page supersedes it. Mark the replacement, or set %2$s back to current.',
                    $page->slug,
                    $link,
                );

                continue;
            }

            if (array_intersect($replacements, $page->links()) === []) {
                $failures[] = sprintf(
                    '%s links to the superseded page [[%s]] without linking its replacement. Link [[%s]] as well, or instead.',
                    $page->slug,
                    $link,
                    implode(']] or [[', $replacements),
                );
            }
        }

        return $failures;
    }

    /**
     * Rule 5. The page claims to describe code that has changed since it was
     * last written. Compared by date, so a commit on the day the page was
     * updated is not drift.
     *
     * @return list<string>
     */
    private function pageOutrunByItsCode(WikiPage $page): array
    {
        $updated = $page->updated();

        if ($updated === null) {
            return [];
        }

        $failures = [];

        foreach ($page->codeRefs() as $ref) {
            $commit = GitLog::lastCommitTouching($ref);

            if ($commit === null || $commit['date'] <= $updated) {
                continue;
            }

            $failures[] = sprintf(
                '%s is stale: %s changed in %s on %s, and the page says updated: %s. %s',
                $page->slug,
                $ref,
                $commit['hash'],
                $commit['date'],
                $updated,
                self::FIX,
            );
        }

        return $failures;
    }

    /**
     * @param  array<string, WikiPage>  $pages
     * @param  list<string>  $failures
     */
    private function render(array $pages, array $failures): int
    {
        if ($failures === []) {
            $this->components->info(sprintf('%d wiki pages, no rot.', count($pages)));

            return self::SUCCESS;
        }

        $this->newLine();
        $this->components->bulletList($failures);
        $this->components->error(sprintf('%d wiki problem(s) across %d pages.', count($failures), count($pages)));

        return self::FAILURE;
    }

    private function pathOption(): ?string
    {
        $path = $this->option('path');

        return is_string($path) && $path !== '' ? $path : null;
    }
}
