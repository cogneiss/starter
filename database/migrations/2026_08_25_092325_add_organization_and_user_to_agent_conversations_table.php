<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The SDK keys a conversation by a polymorphic participant, which is one
     * dimension short here: the same person in two organizations must not
     * resume the same thread. Both columns are added rather than reused
     * because participant_* is the SDK's, and overloading it would break the
     * moment the participant is not a user.
     *
     * The agent lives on the message rows, so the index that serves
     * "this person's threads in this organization, newest first" is
     * (organization_id, user_id, updated_at).
     */
    public function up(): void
    {
        Schema::table('agent_conversations', function (Blueprint $table): void {
            $table->foreignUuid('organization_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->after('organization_id')->constrained()->cascadeOnDelete();

            $table->index(['organization_id', 'user_id', 'updated_at'], 'agent_conversations_scope_index');
        });
    }

    public function down(): void
    {
        Schema::table('agent_conversations', function (Blueprint $table): void {
            $table->dropIndex('agent_conversations_scope_index');
            $table->dropConstrainedForeignId('user_id');
            $table->dropConstrainedForeignId('organization_id');
        });
    }
};
