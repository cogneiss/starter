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

        /**
         * One pending invitation per email per organization. Partial, so the
         * same person can be re-invited after they accepted and left.
         */
        DB::statement(
            'create unique index organization_invitations_pending_unique
             on organization_invitations (organization_id, email)
             where accepted_at is null'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_invitations');
    }
};
