<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only audit trail. Impersonation without one is an unlogged
     * privilege escalation, so nothing here is ever updated except ended_at.
     */
    public function up(): void
    {
        Schema::create('impersonation_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('impersonator_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('impersonated_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('impersonation_logs');
    }
};
