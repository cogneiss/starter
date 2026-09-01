<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per authenticated API request. Deliberately narrow: method, path,
     * resource key, status and duration — never a request body, query value or
     * header, so the usage log cannot become an accidental PII store. Append-only
     * like ai_audit_logs: no updated_at, and the model refuses update()/delete().
     */
    public function up(): void
    {
        Schema::create('api_request_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('api_token_id')->nullable()->constrained('personal_access_tokens')->nullOnDelete();
            $table->string('method', 10);
            $table->string('path');
            $table->string('resource')->nullable();
            $table->unsignedSmallInteger('status');
            $table->unsignedInteger('duration_ms')->default(0);
            $table->timestamp('created_at');

            $table->index(['organization_id', 'created_at']);
            $table->index(['api_token_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_request_logs');
    }
};
