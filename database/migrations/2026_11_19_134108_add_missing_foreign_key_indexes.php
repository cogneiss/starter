<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PostgreSQL does not index a foreign key column, so each of these was
     * scanned on every delete of its parent row and on every join. Eight of
     * them are organization_id on tables whose fail-closed scope filters by
     * it. One index per column, nothing else changes. MySQL indexes a foreign
     * key column itself and refuses to drop an index a foreign key needs, so
     * there this migration adds and removes nothing.
     *
     * Each index build blocks writes to its table until it finishes, so on a
     * database that holds rows this runs in a quiet window.
     *
     * @var array<string, list<string>>
     */
    private const array INDEXES = [
        'agent_conversations' => ['user_id'],
        'ai_confirm_tokens' => ['user_id'],
        'ai_memories' => ['user_id'],
        'announcement_dismissals' => ['user_id'],
        'announcements' => ['organization_id'],
        'impersonation_logs' => ['organization_id', 'impersonator_user_id', 'impersonated_user_id'],
        'import_batches' => ['organization_id', 'user_id', 'temp_upload_id'],
        'model_has_permissions' => ['permission_id'],
        'model_has_roles' => ['role_id'],
        'notifications' => ['organization_id'],
        'onboarding_progress' => ['user_id'],
        'organization_invitations' => ['organization_id', 'invited_by_user_id'],
        'organization_memberships' => ['user_id'],
        'personal_access_tokens' => ['organization_id', 'created_by'],
        'role_has_permissions' => ['role_id'],
        'saved_searches' => ['user_id'],
        'social_accounts' => ['user_id'],
        'temp_uploads' => ['organization_id', 'user_id'],
        'users' => ['current_organization_id'],
        'webhook_deliveries' => ['webhook_endpoint_id'],
        'webhook_endpoints' => ['organization_id', 'created_by'],
    ];

    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            return;
        }

        foreach (self::INDEXES as $tableName => $columns) {
            Schema::table($tableName, function (Blueprint $table) use ($columns): void {
                foreach ($columns as $column) {
                    $table->index($column);
                }
            });
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            return;
        }

        foreach (self::INDEXES as $tableName => $columns) {
            Schema::table($tableName, function (Blueprint $table) use ($columns): void {
                foreach ($columns as $column) {
                    $table->dropIndex([$column]);
                }
            });
        }
    }
};
