<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Platform-level admin audit entries belong to no tenant, so the column that
 * says whose ledger a row is in must be allowed to say "nobody's". Tenant rows
 * keep their organization and their cascade; a null row is visible only to the
 * admin control plane, which reads without the organization scope.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_log', function (Blueprint $table): void {
            $table->uuid('organization_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('activity_log', function (Blueprint $table): void {
            $table->uuid('organization_id')->nullable(false)->change();
        });
    }
};
