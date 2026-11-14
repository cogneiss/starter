<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_audit_logs', function (Blueprint $table): void {
            $table->string('run_id', 64)->nullable()->after('user_id');
            $table->json('tool_arguments')->nullable()->after('tool_calls');
            $table->text('error')->nullable()->after('blocked_reason');
            $table->index(['user_id', 'created_at', 'run_id']);
            $table->index(['organization_id', 'created_at', 'run_id']);
        });
    }

    public function down(): void
    {
        Schema::table('ai_audit_logs', function (Blueprint $table): void {
            $table->dropIndex(['user_id', 'created_at', 'run_id']);
            $table->dropIndex(['organization_id', 'created_at', 'run_id']);
            $table->dropColumn(['run_id', 'tool_arguments', 'error']);
        });
    }
};
