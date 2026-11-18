## Summary

What changed and why, in a few lines.

## Acceptance criteria

Each row is one observable outcome and the command that decides it. The auditor
job grades the diff against these, so write them before the code.

| id | Criterion | Check |
| --- | --- | --- |
| c1 | REPLACE: what must be true when this merges | REPLACE: the exact command, e.g. `vendor/bin/pest tests/Feature/FooTest.php` |
