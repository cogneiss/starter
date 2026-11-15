<?php

declare(strict_types=1);

use App\Models\Role;
use Spatie\Permission\Models\Permission;

/**
 * Only the values that differ from spatie/laravel-permission's defaults. The
 * package merges its own config underneath this file one level deep, so each
 * key here replaces the whole default array of the same name.
 */
return [
    'models' => [
        'permission' => Permission::class,
        'role' => Role::class,
    ],

    /**
     * Teams are organizations: every role and assignment carries the
     * organization it belongs to.
     */
    'column_names' => [
        'model_morph_key' => 'model_id',
        'team_foreign_key' => 'organization_id',
    ],

    'teams' => true,
];
