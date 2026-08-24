# Contributing

Thanks for taking the time. This page is everything you need to get a pull
request merged.

## Setup

```bash
composer setup   # install, key, migrate, build — also wires up the git hooks
composer dev     # server, queue, logs and Vite
```

`php artisan app:doctor` tells you what is wrong with the machine if any of that
misbehaves. Longer setup notes live in [SETUP.md](SETUP.md).

## Commit messages

Conventional commits, enforced by the `commit-msg` hook:

```
feat(auth): add passkey sign-in
fix: stop the invite mailer double-sending
feat(auth)!: drop password login        # breaking change
```

Types: `feat`, `fix`, `docs`, `style`, `refactor`, `perf`, `test`, `build`,
`ci`, `chore`, `revert`. The scope is optional. These are not cosmetic —
release-please reads them to work out the next version and to write the
changelog.

## Formatting

Do not spend time on it. Push your branch and the autofix bot runs Rector, Pint
and the frontend formatter and commits the result back. Locally,
`composer lint` does the same thing. Formatting is never a review comment here.

(The bot is skipped on pull requests from forks, on purpose — it would need
write access to a branch we do not control. On a fork, run `composer lint`
yourself before pushing.)

## What blocks a pull request

All of these run on every pull request and all of them have to be green:

| Gate                      | Checks                                                                  |
| ------------------------- | ----------------------------------------------------------------------- |
| `composer test`           | Lint, type coverage, PHPStan at max, the suite at exactly 100% coverage |
| `composer test:a11y`      | axe-core at level 3 over every page the kit ships                       |
| `composer test:audit`     | `composer audit` and `bun audit`                                        |
| `composer test:dead-code` | Code nothing reaches                                                    |
| `composer test:deps`      | Composer packages nothing requires                                      |
| `composer test:knip`      | Unused frontend files, exports and dependencies                         |
| `composer test:wiki`      | Wiki pages that cite a moved file, or that have gone stale              |
| gitleaks                  | Anything that looks like a credential                                   |

Run `composer test` before you push. `composer test:fast` is the quick loop
while you work.

Postgres and mutation runs happen on a schedule and never block you.

## Tests are not optional

The coverage gate is `--exactly=100.0` — an exact match, not a minimum. New code
without tests fails the build, and so does code that is now unreachable. If you
add a branch, add the test that takes it.

Accessibility is a gate too. If you add a page, add it to
`tests/Browser/AccessibilityTest.php` with its own distinct title.

## Documentation

New code wants a wiki page, or a paragraph in an existing one. `php artisan
wiki:audit` lists application files no page mentions and `/document` writes the
prose from the code. This half is reported, never enforced: blocking on "this
new file has no page" buys one-line pages written to clear a gate.

Changing code an existing page cites **is** enforced. `composer test:wiki` fails
when a file named in a page's `code_refs` has changed since the page was last
updated, so the page belongs in the same pull request as the change, not a
follow-up. A refactor touching ten cited files turns ten pages red at once —
run `/document` as part of the refactor rather than at the end of it. Bumping a
page's `updated:` date without rereading the page clears the gate and leaves the
wrong claim in place; that is the one way to defeat this check, so do not.

## If a gate fails

Fix the code, or record the finding in the tool's baseline **with a reason**.
Never lower a threshold to make a gate pass — not the coverage number, not a
mutation minimum. [SETUP.md](SETUP.md#when-a-gate-fails) has a table of what
each failure means and how to baseline it properly.

## Releases

Maintainers merge the open release-please pull request; that tags the version,
writes the changelog and attaches the SBOM. Nothing is released by pushing to
`main`.
