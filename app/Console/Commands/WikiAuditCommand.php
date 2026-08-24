<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\GitLog;
use App\Support\WikiPage;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * The documentation worklist. `wiki:lint` blocks on pages that contradict the
 * code; this command only reports, and it reports the work no gate should force:
 * files nobody has documented and pages whose code is gone.
 *
 * No language model runs here or in CI. This half is deterministic and produces
 * `wiki/_meta/audit.json`; `/document` consumes that file on a developer's own
 * machine and writes the prose.
 */
#[Description('Write wiki/_meta/audit.json: which pages are stale, which files are undocumented')]
#[Signature('wiki:audit {--path= : The wiki directory to audit, defaults to wiki/}')]
final class WikiAuditCommand extends Command
{
    public function handle(): int
    {
        $root = $this->pathOption() ?? base_path('wiki');

        $pages = WikiPage::in($root);

        $audit = [
            'generated_from' => GitLog::head(),
            'stale' => $this->stale($pages),
            'undocumented' => $this->undocumented($pages),
            'orphaned' => $this->orphaned($pages),
        ];

        $out = $root.'/_meta/audit.json';

        File::ensureDirectoryExists(dirname($out));
        File::put($out, json_encode($audit, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

        $this->components->info(sprintf(
            '%d stale page(s), %d undocumented file(s), %d orphaned page(s). Worklist: %s',
            count($audit['stale']),
            count($audit['undocumented']),
            count($audit['orphaned']),
            $out,
        ));

        return self::SUCCESS;
    }

    /**
     * Pages whose code has been committed since the page was last written. Same
     * comparison as lint rule 5, so the two never disagree about what is stale.
     *
     * @param  array<string, WikiPage>  $pages
     * @return list<array{page: string, reason: string, code_refs: list<string>}>
     */
    private function stale(array $pages): array
    {
        $stale = [];

        foreach ($pages as $page) {
            $updated = $page->updated();

            if ($updated === null) {
                continue;
            }

            foreach ($page->codeRefs() as $ref) {
                $commit = GitLog::lastCommitTouching($ref);

                if ($commit === null || $commit['date'] <= $updated) {
                    continue;
                }

                $stale[] = [
                    'page' => $page->slug,
                    'reason' => sprintf('%s changed in %s', $ref, $commit['hash']),
                    'code_refs' => $page->codeRefs(),
                ];
            }
        }

        return $stale;
    }

    /**
     * Application files no page claims. Reported, never blocked: a gate here
     * buys one-line pages written to clear it.
     *
     * @param  array<string, WikiPage>  $pages
     * @return list<array{path: string, reason: string}>
     */
    private function undocumented(array $pages): array
    {
        $documented = [];

        foreach ($pages as $page) {
            foreach ($page->codeRefs() as $ref) {
                $documented[$ref] = true;
            }
        }

        $undocumented = [];

        foreach (File::allFiles(app_path()) as $file) {
            $path = 'app/'.str_replace(DIRECTORY_SEPARATOR, '/', $file->getRelativePathname());

            if (array_key_exists($path, $documented)) {
                continue;
            }

            $undocumented[] = [
                'path' => $path,
                'reason' => 'no wiki page lists this file in code_refs',
            ];
        }

        return $undocumented;
    }

    /**
     * Pages left describing code that no longer exists. `/document` supersedes
     * them; nothing here deletes a page.
     *
     * @param  array<string, WikiPage>  $pages
     * @return list<array{page: string, reason: string}>
     */
    private function orphaned(array $pages): array
    {
        $orphaned = [];

        foreach ($pages as $page) {
            $refs = $page->codeRefs();

            $missing = array_filter($refs, fn (string $ref): bool => ! file_exists(base_path($ref)));

            if ($refs !== [] && count($missing) === count($refs)) {
                $orphaned[] = [
                    'page' => $page->slug,
                    'reason' => 'all code_refs deleted',
                ];
            }
        }

        return $orphaned;
    }

    private function pathOption(): ?string
    {
        $path = $this->option('path');

        return is_string($path) && $path !== '' ? $path : null;
    }
}
