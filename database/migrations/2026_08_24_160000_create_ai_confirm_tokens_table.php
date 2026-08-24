<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A model never writes. It proposes, and the person confirms. One row here
     * is one proposal: what would run, for whom, signed so it cannot be edited
     * between the two halves, and consumable exactly once.
     */
    public function up(): void
    {
        Schema::create('ai_confirm_tokens', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('action');
            $table->text('payload');
            $table->string('signature');
            $table->string('summary');
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('created_at');

            $table->index(['organization_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_confirm_tokens');
    }
};
