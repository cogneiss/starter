<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Money moves in signed integer micros and only ever forward: a grant is a
     * positive row, a charge is a negative one, and the balance is the
     * balance_micros_after of the newest row. Nothing is denormalised onto
     * organizations, so a lost update cannot invent credit.
     */
    public function up(): void
    {
        Schema::create('ai_credit_ledger', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('delta_micros');
            $table->string('reason');
            $table->string('reference_type')->nullable();
            $table->uuid('reference_id')->nullable();
            $table->bigInteger('balance_micros_after');
            $table->timestamp('created_at');

            $table->index(['organization_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_credit_ledger');
    }
};
