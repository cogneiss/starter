<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Process;

/**
 * The two git questions the documentation gates ask: what is HEAD, and when did
 * this file last change. Both answer null rather than throwing, because a
 * tarball checkout with no `.git` is a legitimate way to run this application
 * and neither gate is worth a crash there.
 */
final class GitLog
{
    /**
     * @return array{hash: string, date: string}|null
     */
    public static function lastCommitTouching(string $ref): ?array
    {
        $log = self::git(['git', 'log', '-1', '--date=short', '--format=%h %ad', '--', $ref]);

        if ($log === null) {
            return null;
        }

        $parts = explode(' ', $log);

        return count($parts) === 2 ? ['hash' => $parts[0], 'date' => $parts[1]] : null;
    }

    public static function head(): ?string
    {
        return self::git(['git', 'rev-parse', '--short', 'HEAD']);
    }

    /**
     * @param  list<string>  $command
     */
    private static function git(array $command): ?string
    {
        $result = Process::path(base_path())->run($command);

        return $result->successful() ? mb_trim($result->output()) : null;
    }
}
