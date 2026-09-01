# Project Rules Index

Before planning or editing, find the row whose globs match the file's path and read that rule file.

| Applies to                                                                                                                                                        | Rule file                 |
| ----------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------- |
| app/Ai/**, app/Mcp/**, config/ai.php, tests/Evals/**                                                                                                              | .ai/rules/ai.md           |
| app/**                                                                                                                                                            | .ai/rules/app.md          |
| bootstrap/**                                                                                                                                                      | .ai/rules/bootstrap.md    |
| .github/**, composer.json, package.json                                                                                                                           | .ai/rules/ci.md           |
| wiki/**, .ai/**, *.md                                                                                                                                             | .ai/rules/docs.md         |
| **, {AGENTS,CLAUDE,GEMINI}.md                                                                                                                                     | .ai/rules/general.md      |
| app/Policies/**                                                                                                                                                   | .ai/rules/policies.md     |
| app/Models/**, app/Data/**, app/Resources/**, app/Http/Controllers/**                                                                                             | .ai/rules/resources.md    |
| routes/api.php, app/Http/Controllers/Api/**, app/Webhooks/**, app/Admin/**, app/Support/{Analytics,Reporting,Health}/**, config/{api,webhooks,audit}.php          | .ai/rules/platform-ops.md |
| app/Support/ResourceQuery.php, app/Http/Controllers/Concerns/**, app/Onboarding/**, app/Imports/**, resources/js/components/data-table\*.tsx, resources/js/lib/** | .ai/rules/ux.md           |
