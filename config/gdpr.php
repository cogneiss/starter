<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Exported tables
    |--------------------------------------------------------------------------
    |
    | Every table holding rows keyed by a user_id column that a personal-data
    | export copies into the archive. A test enumerates information_schema for
    | user_id columns and fails when a table is neither listed here nor in the
    | reasoned exclusions below, so a new table forces a decision.
    |
    */

    'tables' => [
        'agent_conversations',
        'ai_audit_logs',
        'ai_confirm_tokens',
        'ai_memories',
        'import_batches',
        'login_histories',
        'onboarding_progress',
        'organization_memberships',
        'passkeys',
        'saved_searches',
        'social_accounts',
        'temp_uploads',
    ],

    /*
    |--------------------------------------------------------------------------
    | Reasoned exclusions
    |--------------------------------------------------------------------------
    */

    'excluded' => [
        'sessions' => 'Serialized framework session state, regenerated on every login; nothing durable or human-readable.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Purge window
    |--------------------------------------------------------------------------
    |
    | Days a soft-deleted, anonymised account is kept before gdpr:purge hard
    | deletes the row and everything cascading from it.
    |
    */

    'purge_after_days' => (int) (env('GDPR_PURGE_AFTER_DAYS') ?: 30),

];
