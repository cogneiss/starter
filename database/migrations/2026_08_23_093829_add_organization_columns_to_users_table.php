<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignUuid('current_organization_id')
                ->nullable()
                ->after('email_verified_at')
                ->constrained('organizations')
                ->nullOnDelete();
            $table->boolean('is_active')->default(true)->after('current_organization_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('current_organization_id');
            $table->dropColumn('is_active');
        });
    }
};
