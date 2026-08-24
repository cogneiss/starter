---
description: Update wiki/ from the code, working the wiki:audit worklist
allowed-tools: Bash(php artisan wiki:audit), Bash(php artisan wiki:lint), Bash(git log:*), Bash(git show:*), Read, Edit, Write, Grep, Glob
---

# /document

`wiki/` is the knowledge base an agent reads before it changes anything here, so
a page that reads confidently and is wrong is worse than no page. This command
brings the wiki back in line with the code, and nothing else.

The worklist is produced by `php artisan wiki:audit`, which is deterministic and
runs in CI and after every commit. This command is the other half: it writes the
prose. Run `php artisan wiki:audit` first if `wiki/_meta/audit.json` looks old.

## Procedure

1. Read `wiki/_meta/audit.json`. That file is the scope of this run.
2. For each entry in `stale`: read the page, read every file in its `code_refs`,
   run `git log --oneline <updated-date>..HEAD -- <ref>` to see what changed,
   rewrite the sections the change touched, then set `updated:` to today.
3. For each entry in `undocumented`: read the file, then either extend the
   existing page that already covers its area — adding the path to that page's
   `code_refs` — or write a new page using the frontmatter shape in
   `wiki/_meta/lint.md`. One new page per area, not one per file.
4. For each entry in `orphaned`: set `status: superseded` on the page and add a
   line at the top saying what replaced it. **Never delete a page.** Links into
   it, including ones in other people's notes, have to keep landing somewhere
   true.
5. Run `php artisan wiki:lint` and fix everything it reports. A link to a
   superseded page must also link the page that supersedes it.
6. Report every page touched, one line each, and say which audit entries were
   left alone and why.

## Hard constraints

- **Never invent behaviour.** Every claim in a page names the file that proves
  it, and that file was read in this run. Uncertain means "not documented" —
  write that, rather than a plausible guess. A guess is indistinguishable from a
  fact to the next reader.
- **Never bump `updated:` on its own.** It clears the stale gate and leaves the
  page wrong, which is the one outcome worse than the page being flagged. Reread
  the code first, always.
- **Never edit generated files:** `AGENTS.md`, `CLAUDE.md`, `GEMINI.md`,
  `resources/js/types/generated.d.ts`, `CHANGELOG.md`,
  `wiki/_meta/audit.json`. Change the source and regenerate.
- **Never touch a `status: current` page that the audit did not list.** The
  worklist is the scope. A page nothing flagged is a page nothing says is wrong.
- **Prose only.** Files may be written under `wiki/`, plus `README.md`,
  `SETUP.md` and `FEATURES.md`. No code changes, no test changes, no config
  changes. If the code is wrong, say so in the report and stop there.
- `code_refs` are file paths, never directories. A directory ref goes stale on
  every unrelated commit inside it, and a gate that goes red for unrelated
  reasons gets switched off.
