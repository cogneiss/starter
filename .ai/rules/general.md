---
paths:
    - "**"
    - "{AGENTS,CLAUDE}.md"
---

# General

## Query Laravel Brain for wiring questions

Laravel Brain, rebuilt by `.githooks/post-commit`, answers what grep cannot see: which controller a route binds to, which listeners an event has, which job is queued, a model's relationships. For those, pull the slice first. For plain text or symbol search, grep is fine.

```bash
php artisan brain:export-context --route=/settings/profile --budget=2000
```

The URI needs its leading slash. `--route=dashboard` silently falls back to a full-project dump, so check the second output line says `Focal:` with your route, not `Full project summary`.

Do not run `brain:generate-rules`. It writes a snapshot into `CLAUDE.md` that goes stale on the next commit and is re-read every turn.

`storage/app/laravel-brain` is gitignored and machine-local. Never read it into context; use the CLI. If the graph is stale or missing, run `php artisan brain:scan --no-interaction`.

## The agent guideline files are generated output, never edit them

`.ai/guidelines/*.blade.php` plus the package guidelines Boost ships are the source. `AGENTS.md` and the published skills directories (`.claude/skills`, `.agents/skills`) are output, and `CLAUDE.md` is the one line `@AGENTS.md`. An edit inside the `<laravel-boost-guidelines>` block is gone on the next render, so change the blade file and regenerate:

```bash
php artisan boost:install --guidelines --skills --no-interaction
php artisan agents:context
```

Both flags are required. `--guidelines` alone drops the "Skills Activation" section, which is what routes an agent to the skill packs in `.ai/skills`.

Two things keep this honest so you rarely have to remember it: `composer update` runs the render through `post-update-cmd`, and `tests/Feature/Docs/GuidelinesAreCurrentTest.php` fails when a generated file no longer matches its source, naming the command that fixes it. The test renders in a subprocess and restores the files afterwards, so a red run tells you about the drift instead of hiding it.
