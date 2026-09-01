<?php

declare(strict_types=1);

use App\Models\AiAuditLog;
use App\Models\AiCreditLedgerEntry;
use App\Models\AiDocument;
use App\Models\AiMemory;
use App\Models\ApiRequestLog;
use App\Models\ApiToken;
use App\Models\FeatureOverride;
use App\Models\ImpersonationLog;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\LoginHistory;
use App\Models\OnboardingProgress;
use App\Models\Role;
use App\Models\RoleTemplate;
use App\Models\SavedSearch;
use App\Models\SocialAccount;
use App\Models\TempUpload;

return [
    /** Models with no factory — pivots and framework-owned tables. */
    'models_without_factory' => [
        Role::class => 'Spatie permission role, written by RoleTemplateSeeder rather than by a factory.',
    ],

    /** Models with no resource adapter, with a reason. G5 fails on anything absent from here. */
    'non_resource_models' => [
        AiAuditLog::class => 'Append-only AI usage log, reported in aggregate by ai:usage, never linked.',
        AiCreditLedgerEntry::class => 'Append-only AI credit ledger, summed for a balance, never listed as a record.',
        AiDocument::class => 'Retrieval corpus rows, reached only through an agent tool, never listed or linked.',
        AiMemory::class => "One person's private assistant memory, written by one tool and read into their own prompt, never listed or linked.",
        ApiRequestLog::class => 'Append-only API usage log, reported in aggregate on the usage page, never listed or linked.',
        ApiToken::class => 'A credential, not a record: shown only on its own settings page, never listed or linked as data.',
        FeatureOverride::class => 'pending resource adapter',
        ImpersonationLog::class => 'Append-only audit table, never listed or linked.',
        ImportBatch::class => 'One run of an import, shown by its own progress page, never listed as a record.',
        ImportRow::class => 'A single line of an uploaded file, reached only through its batch.',
        LoginHistory::class => 'Append-only audit table, read through UserData, never linked.',
        OnboardingProgress::class => "One person's decision to skip the activation checklist, read by the gate, never listed as a record.",
        Role::class => 'pending resource adapter',
        RoleTemplate::class => 'Seed data for new organizations, not a user-facing record.',
        SavedSearch::class => "One person's kept views of a list, offered by the list itself, never listed as records of their own.",
        SocialAccount::class => 'Provider link shown on the profile page, never listed on its own.',
        TempUpload::class => 'A file waiting for a scanner, replaced by whatever it becomes, never listed as a record.',
    ],
];
