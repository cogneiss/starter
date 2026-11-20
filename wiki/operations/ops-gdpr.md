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
    - app/Notifications/GdprExportReady.php
updated: 2026-11-20
---

# GDPR export and deletion

Two obligations, two mechanisms: access (export what we hold) and erasure
(stop holding it).

## Export

A user requests their export from settings; `BuildGdprExport` runs on the
queue and assembles a ZIP of their personal data across the models that hold
any. The download link is signed and expires. When the ZIP is built, `BuildGdprExport` notifies the user with `GdprExportReady`, an in-app (database) notification whose link is a temporary signed route to `gdpr-export.download`, valid for one day; per its docblock the file name alone is useless without the signature and the requester's own session. `php artisan gdpr:export
<user>` queues the same job from the console for a request that arrives by
email instead of by click. `<user>` is a user id or an email address, and
`app/Console/Commands/GdprExportCommand.php` lower-cases it before the lookup,
so an address pasted from the request in capitals still finds the account.

## Deletion

Erasure is anonymisation first, destruction second. `DeleteAccount` strips
the personal fields and marks the account anonymised, which keeps the audit
trail ([[operations/ops-audit-log]]) and the organization's history coherent
— rows still say _someone_ did the thing, just no longer who. `php artisan
gdpr:purge` then hard-deletes anonymised accounts once the retention window
passes, so "we keep nothing" eventually becomes literally true.

A failed sign-in has no user id, so `DeleteAccount` finds those login-history
rows by address. `LoginHistory` stores the address in lower case
([[domains/models]]), which is why an attempt typed as `Ada@Example.com` is
anonymised with the rest. `tests/Feature/GdprDeleteTest.php` holds that case.

The two-step exists because immediate hard deletion would either break
foreign keys across the tenancy or silently rewrite history; anonymisation
does neither, and the purge window bounds how long the husk survives.
