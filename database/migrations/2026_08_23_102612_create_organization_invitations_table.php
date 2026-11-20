<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /**
         * One pending invitation per email per organization. Partial, so the
         * same person can be re-invited after they accepted and left. MySQL
         * has no partial index, so there the second key part is an expression
         * that is null once the invitation is accepted. An engine with neither
         * form stops here, before the table exists without its rule.
         */
        $pendingUnique = match (DB::connection()->getDriverName()) {
            'pgsql', 'sqlite' => 'create unique index organization_invitations_pending_unique
             on organization_invitations (organization_id, email)
             where accepted_at is null',
            'mysql' => 'create unique index organization_invitations_pending_unique
             on organization_invitations (organization_id, (if(accepted_at is null, email, null)))',
            default => throw new RuntimeException('No pending-invitation unique index is written for this database driver.'),
        };

        /**
         * The same check as on users.email. SQLite goes without. An engine
         * with no form written has already stopped at the match above.
         */
        $emailChecks = match (DB::connection()->getDriverName()) {
            'pgsql' => [
                "alter table organization_invitations add constraint organization_invitations_email_lowercase check (email !~ '[A-Z]')",
            ],
            'mysql' => [
                "alter table organization_invitations add constraint organization_invitations_email_lowercase check (not regexp_like(email, '[A-Z]', 'c'))",
            ],
            'sqlite' => [],
        };

        Schema::create('organization_invitations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('role');
            $table->string('token')->unique();
            $table->foreignUuid('invited_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
        });

        DB::statement($pendingUnique);

        foreach ($emailChecks as $emailCheck) {
            DB::statement($emailCheck);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_invitations');
    }
};
