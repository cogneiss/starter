<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What a person decided about onboarding, and nothing else.
     *
     * Whether a step is done is not stored here. Each step answers that from the
     * data it is about — the organization has brand colours, an invitation went
     * out — so a step finished through its ordinary route counts the moment it
     * happens and no read has to write a row to stay honest.
     *
     * What is left is the part the application cannot derive: this person, in
     * this organization, chose to skip the checklist or has seen it through. One
     * row per pair, created only when somebody decides something.
     */
    public function up(): void
    {
        Schema::create('onboarding_progress', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('skipped_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            // One decision per person per organization, enforced rather than
            // hoped for: the gate reads this on every request.
            $table->unique(['organization_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_progress');
    }
};
