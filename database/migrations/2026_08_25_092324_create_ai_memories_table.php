<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What the assistant is allowed to remember about one person.
     *
     * The unique key is (organization_id, user_id, key), which is the whole
     * privacy model written as a constraint: a fact belongs to one person in
     * one organization, and remembering the same key again overwrites their
     * row rather than leaking into anyone else's.
     */
    public function up(): void
    {
        Schema::create('ai_memories', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->text('value');
            $table->string('source');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'user_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_memories');
    }
};
