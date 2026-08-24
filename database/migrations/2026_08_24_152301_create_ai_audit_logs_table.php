<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per agent invocation, including the ones that were rejected. The
     * table is append-only on purpose: it is what quota, spend and abuse
     * questions are answered from, so there is no updated_at and no soft
     * delete. App\Models\AiAuditLog refuses update() and delete() to match.
     */
    public function up(): void
    {
        Schema::create('ai_audit_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('agent');
            $table->string('provider')->nullable();
            $table->string('model')->nullable();
            $table->string('tier')->nullable();
            $table->unsignedInteger('prompt_tokens')->default(0);
            $table->unsignedInteger('completion_tokens')->default(0);
            $table->unsignedInteger('total_tokens')->default(0);
            $table->unsignedBigInteger('cost_micros')->default(0);
            $table->unsignedInteger('duration_ms')->default(0);
            $table->string('status');
            $table->string('blocked_reason')->nullable();
            $table->json('tool_calls')->nullable();
            $table->timestamp('created_at');

            $table->index(['organization_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_audit_logs');
    }
};
