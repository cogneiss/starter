<?php

declare(strict_types=1);

namespace App\Support;

use Symfony\Component\Yaml\Yaml;

/**
 * One markdown page in `wiki/`, plus the frontmatter that makes it lintable.
 *
 * Pages under `_meta/` describe the rules rather than the application, so they
 * carry no frontmatter and are not pages in this sense — they are skipped.
 */
final readonly class WikiPage
{
    /**
     * @param  array<string, mixed>  $frontmatter
     */
    public function __construct(
        public string $slug,
        public array $frontmatter,
        public string $body,
    ) {}

    /**
     * Every page in the wiki, keyed by slug, in a stable order.
     *
     * @return array<string, self>
     */
    public static function in(string $root): array
    {
        $files = glob(mb_rtrim($root, '/').'/{*.md,*/*.md}', GLOB_BRACE);

        $pages = [];

        foreach ($files === false ? [] : $files as $file) {
            $slug = mb_substr(mb_substr($file, mb_strlen(mb_rtrim($root, '/')) + 1), 0, -3);

            if (str_starts_with($slug, '_meta/')) {
                continue;
            }

            $pages[$slug] = self::fromFile($slug, $file);
        }

        ksort($pages);

        return $pages;
    }

    public static function fromFile(string $slug, string $path): self
    {
        $contents = (string) file_get_contents($path);

        if (! str_starts_with($contents, "---\n")) {
            return new self($slug, [], $contents);
        }

        $end = mb_strpos($contents, "\n---", 4);

        if ($end === false) {
            return new self($slug, [], $contents);
        }

        $parsed = rescue(
            fn (): mixed => Yaml::parse(mb_substr($contents, 4, $end - 4)),
            null,
            report: false,
        );

        $frontmatter = [];

        foreach (is_array($parsed) ? $parsed : [] as $key => $value) {
            if (is_string($key)) {
                $frontmatter[$key] = $value;
            }
        }

        return new self($slug, $frontmatter, mb_substr($contents, $end + 4));
    }

    public function status(): string
    {
        $status = $this->frontmatter['status'] ?? null;

        return is_string($status) ? $status : '';
    }

    public function superseded(): bool
    {
        return $this->status() === 'superseded';
    }

    /**
     * The `updated:` date as `Y-m-d`.
     *
     * An unquoted YAML date parses to a Unix timestamp rather than a string, so
     * reading it as a string alone would silently return null here and switch
     * the stale-page rule off for every page in the wiki.
     */
    public function updated(): ?string
    {
        $updated = $this->frontmatter['updated'] ?? null;

        return match (true) {
            is_string($updated) => $updated,
            is_int($updated) => gmdate('Y-m-d', $updated),
            default => null,
        };
    }

    /**
     * @return list<string>
     */
    public function codeRefs(): array
    {
        return $this->stringList('code_refs');
    }

    /**
     * @return list<string>
     */
    public function supersedes(): array
    {
        return $this->stringList('supersedes');
    }

    /**
     * The slugs this page links to with `[[wikilinks]]`.
     *
     * @return list<string>
     */
    public function links(): array
    {
        preg_match_all('/\[\[([^\]|]+)(?:\|[^\]]*)?\]\]/', $this->body, $matches);

        return array_values(array_unique(array_map(mb_trim(...), $matches[1])));
    }

    /**
     * @param  list<string>  $required
     * @return list<string>
     */
    public function missingKeys(array $required): array
    {
        return array_values(array_filter(
            $required,
            fn (string $key): bool => ! array_key_exists($key, $this->frontmatter),
        ));
    }

    /**
     * @return list<string>
     */
    private function stringList(string $key): array
    {
        $value = $this->frontmatter[$key] ?? [];

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, is_string(...)));
    }
}
