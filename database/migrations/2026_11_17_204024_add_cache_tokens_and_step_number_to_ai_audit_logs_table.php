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
            $table->unsignedInteger('cache_read_tokens')->default(0)->after('completion_tokens');
            $table->unsignedInteger('cache_write_tokens')->default(0)->after('cache_read_tokens');
            $table->unsignedSmallInteger('step_number')->nullable()->after('run_id');
            $table->unique(['run_id', 'step_number']);
        });
    }

    public function down(): void
    {
        Schema::table('ai_audit_logs', function (Blueprint $table): void {
            $table->dropUnique(['run_id', 'step_number']);
            $table->dropColumn(['cache_read_tokens', 'cache_write_tokens', 'step_number']);
        });
    }
};
