---
title: Decision log — the resource spine
status: current
supersedes: []
code_refs:
    - todo/resource-spine.status.json
updated: 2026-08-24
---

# Decision log — the resource spine

Nine phases, all `passing` in `todo/resource-spine.status.json`: typed payloads
with laravel-data, the resource contract and registry, the `app:make-resource`
generator, convention guards G1/G4/G5, the semantic value components,
`app:doctor`, the generator quality bar, documentation, and the full gate.

## The decisions

- **Six methods, no more.** The contract stayed at the smallest set with a real
  consumer. Every method a package would have added was cut with a written reason
  ([[architecture/six-method-spine]], [[domains/resources]]).
- **One type source.** PHP `#[TypeScript]` classes generate the TypeScript; the
  declarations are an output ([[architecture/type-safety]]).
- **The generator's output must pass the gate unedited.** That is why it emits
  `create`/`store` and four tests rather than a full CRUD set nothing covers —
  scaffolding that fails coverage teaches people to disable the gate
  ([[domains/resources]]).
- **Guards carry reasons.** A convention guard prints why the convention exists,
  not just that it was violated ([[architecture/convention-guards]]).
- **Value components render, they do not animate.** The motion layer was cut
  ([[features/interface]]).

## The coverage caveat recorded by that plan

`composer test:unit` runs every suite under one coverage report, and the Browser
suite's worker processes drop the coverage other workers recorded, so the
all-suite total lands at 88–91% regardless of the code under test — 88.7% measured
on a clean tree before that plan started. The honest gate is the same command
scoped to the instrumented suites:

```bash
herd coverage vendor/bin/pest --parallel --coverage --exactly=100.0 --testsuite=Unit,Feature
```

which is at 100.0%. The threshold was not lowered
([[architecture/fast-blocking-gates]], [[operations/testing]]).

Everything skipped, with reasons — Scout and search, `visibleTo()`,
`actions()`, the REST surface, the resource loom, guard G6 — is in
[[decisions/not-included]].
