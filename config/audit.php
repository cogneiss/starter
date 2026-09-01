<?php

declare(strict_types=1);

use App\Models\AiAuditLog;
use App\Models\AiConfirmToken;
use App\Models\AiCreditLedgerEntry;
use App\Models\AiDocument;
use App\Models\ApiRequestLog;
use App\Models\ApiToken;
use App\Models\FeatureOverride;
use App\Models\ImportBatch;
use App\Models\OnboardingProgress;
use App\Models\OrganizationInvitation;
use App\Models\SavedSearch;
use App\Models\TempUpload;

return [
    /**
     * Models audited on create, update and delete: every model using the
     * BelongsToOrganization concern except the activity model itself. A test
     * reflects over app/Models and fails when this list and the concern's
     * users differ in either direction, so a new scoped model cannot slip in
     * unaudited.
     */
    'models' => [
        AiAuditLog::class,
        AiConfirmToken::class,
        AiCreditLedgerEntry::class,
        AiDocument::class,
        ApiRequestLog::class,
        ApiToken::class,
        ImportBatch::class,
        OnboardingProgress::class,
        OrganizationInvitation::class,
        SavedSearch::class,
        TempUpload::class,
    ],

    /**
     * Models audited despite not using BelongsToOrganization. FeatureOverride
     * is deliberately unscoped — a flag is resolved for a named organization,
     * not the bound one — but flipping a flag is still an auditable act.
     */
    'extra' => [
        FeatureOverride::class,
    ],

    /**
     * Attribute names whose values never reach an audit entry, before or
     * after. Matching is exact and case-sensitive.
     */
    'redact' => [
        'password',
        'remember_token',
        'token',
        'secret',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ],

    /** Days an audit entry is kept before audit:prune deletes it. */
    'retention' => (int) (env('AUDIT_RETENTION_DAYS') ?: 365),
];
