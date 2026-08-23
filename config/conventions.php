<?php

declare(strict_types=1);

use App\Models\FeatureOverride;
use App\Models\ImpersonationLog;
use App\Models\LoginHistory;
use App\Models\Role;
use App\Models\RoleTemplate;
use App\Models\SocialAccount;

return [
    /** Models with no factory — pivots and framework-owned tables. */
    'models_without_factory' => [
        Role::class => 'Spatie permission role, written by RoleTemplateSeeder rather than by a factory.',
    ],

    /** Models with no resource adapter, with a reason. G5 fails on anything absent from here. */
    'non_resource_models' => [
        FeatureOverride::class => 'pending resource adapter',
        ImpersonationLog::class => 'Append-only audit table, never listed or linked.',
        LoginHistory::class => 'Append-only audit table, read through UserData, never linked.',
        Role::class => 'pending resource adapter',
        RoleTemplate::class => 'Seed data for new organizations, not a user-facing record.',
        SocialAccount::class => 'Provider link shown on the profile page, never listed on its own.',
    ],
];
