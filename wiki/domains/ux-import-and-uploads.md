---
title: Bulk import and temporary uploads
status: current
supersedes: []
code_refs:
    - app/Imports/ImportContract.php
    - app/Imports/ImportRegistry.php
    - app/Imports/ImportRunner.php
    - app/Imports/OrganizationInvitationImport.php
    - app/Models/ImportBatch.php
    - app/Models/ImportRow.php
    - app/Models/TempUpload.php
    - app/Actions/PromoteTempUpload.php
    - app/Jobs/ParseImportBatch.php
    - app/Jobs/ScanTempUpload.php
    - app/Support/Scanners/ClamAvScanner.php
    - app/Support/Scanners/NullScanner.php
    - app/Console/Commands/PruneUploadsCommand.php
    - config/uploads.php
    - app/Policies/ImportBatchPolicy.php
    - app/Policies/TempUploadPolicy.php
    - database/migrations/2026_08_30_100000_create_imports_and_temp_uploads_tables.php
    - tests/Feature/Import/ImportPerRecordPolicyMixedBatchTest.php
    - tests/Feature/Import/TempUploadPromoteBlockedUnscannedTest.php
    - tests/Feature/TempUploadScopeTest.php
    - tests/Feature/TempUploadNotDirectlyDownloadableTest.php
    - tests/Feature/PruneUploadsTest.php
    - tests/Mutations/phase13-policy.patch
updated: 2026-08-31
---

# Bulk import and temporary uploads

Someone uploads a spreadsheet, sees what will happen, and the rows are created
one at a time under the same rules a single-record form would apply. Nothing
about the file being large changes who may do what.

## The registry is a written list

`App\Imports\ImportRegistry` names its imports explicitly rather than scanning a
directory. Every other registry in this kit discovers its members, and this one
is the exception on purpose: **imports write**. A class that appears in a
directory should not thereby gain the ability to create records in bulk.

An `ImportContract` declares its columns, and the downloadable template is
generated from that declaration, so the template a person fills in cannot drift
from the columns the parser reads.

## Every row is authorized

`ImportRunner::runRow()` calls `authorizeRow()` for each row. A mixed batch is
the case that matters: rows the person may create are created, rows they may not
are recorded as failures with a reason, and the batch continues.
`tests/Feature/Import/ImportPerRecordPolicyMixedBatchTest.php` covers exactly
that, and `tests/Mutations/phase13-policy.patch` disables the check to prove the
test is what is holding the line:

```bash
bin/prove-control.sh phase13-policy ImportPerRecordPolicyMixedBatch
```

The runner never rethrows. One bad row is a message on that row, not a dead
batch and not a rollback of the rows that already worked. Failed rows are
retryable once the file is fixed.

## Parsing happens on the queue

`ParseImportBatch` is dispatched with the organization bound to it
(`WithOrganizationContext`), so a job that runs minutes later on another machine
resolves the same tenant the request did —
`tests/Feature/ImportJobRebindsOrganizationTest.php`.

## Uploads are quarantined until scanned

A file arrives as a `TempUpload`: stored on a private disk, owned by
`(organization, user)`, and reachable by no URL of its own
(`tests/Feature/TempUploadNotDirectlyDownloadableTest.php`). `ScanTempUpload`
runs on the queue and writes a verdict.

`App\Actions\PromoteTempUpload` refuses anything whose `scan_result` is not
`FileScanner::CLEAN`. **An unscanned upload is refused exactly like an infected
one** — a missing verdict is not permission to proceed, and the file is not read
while the scanner has not reached it.

Which scanner runs is `config/uploads.php`: `null` accepts everything and says
so, `clamav` shells out to a real one. Point `UPLOAD_SCANNER` at `clamav`
anywhere that takes files from people you do not know.

Unpromoted uploads expire after `UPLOAD_TTL_HOURS` (24 by default):

```bash
php artisan uploads:prune
```

Row and file are deleted in the same pass — a row without its file leaves bytes
on disk forever, a file without its row leaves a record pointing at nothing. The
command runs across every organization, because expiry is housekeeping rather
than a tenant's decision, which is why it is scheduled and not reachable from a
request.

## Scoping

Batches, rows and uploads are all found through `(organization, user)` at the
query level. A colleague's batch in your own organization is as absent as
another tenant's, so both answer 404 — see
`tests/Feature/TempUploadScopeTest.php`.

## Related

- [[domains/ux-list-kit]] — the export half of the same round trip
- [[domains/multi-tenancy]] — the scope these lookups start from
- [[operations/runtime]] — where the parse and scan jobs run
