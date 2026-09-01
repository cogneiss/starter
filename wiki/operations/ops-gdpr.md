---
title: GDPR export and deletion
status: current
supersedes: []
code_refs:
    - app/Jobs/BuildGdprExport.php
    - app/Http/Controllers/GdprController.php
    - app/Actions/DeleteAccount.php
    - app/Console/Commands/GdprExportCommand.php
    - app/Console/Commands/GdprPurgeCommand.php
    - tests/Feature/GdprExportTest.php
    - tests/Feature/GdprDeleteTest.php
    - tests/Feature/GdprPurgeTest.php
updated: 2026-09-01
---

# GDPR export and deletion

Two obligations, two mechanisms: access (export what we hold) and erasure
(stop holding it).

## Export

A user requests their export from settings; `BuildGdprExport` runs on the
queue and assembles a ZIP of their personal data across the models that hold
any. The download link is signed and expires. `php artisan gdpr:export
<user>` queues the same job from the console for a request that arrives by
email instead of by click.

## Deletion

Erasure is anonymisation first, destruction second. `DeleteAccount` strips
the personal fields and marks the account anonymised, which keeps the audit
trail ([[operations/ops-audit-log]]) and the organization's history coherent
— rows still say _someone_ did the thing, just no longer who. `php artisan
gdpr:purge` then hard-deletes anonymised accounts once the retention window
passes, so "we keep nothing" eventually becomes literally true.

The two-step exists because immediate hard deletion would either break
foreign keys across the tenancy or silently rewrite history; anonymisation
does neither, and the purge window bounds how long the husk survives.
