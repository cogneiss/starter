<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Two hexes are the whole of a tenant's branding. Everything else in the
     * interface is derived from them, so there is nothing here to keep in sync.
     */
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->string('brand_primary_color', 7)->nullable();
            $table->string('brand_accent_color', 7)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropColumn(['brand_primary_color', 'brand_accent_color']);
        });
    }
};
