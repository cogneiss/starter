<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Users are keyed by uuid, and the SDK stores the participant key as a
     * bigint, so the first saved turn for a member would fail on insert.
     */
    public function up(): void
    {
        foreach ($this->tables() as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->string('participant_id', 36)->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables() as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->unsignedBigInteger('participant_id')->nullable()->using('participant_id::bigint')->change();
            });
        }
    }

    /**
     * @return list<string>
     */
    private function tables(): array
    {
        return [
            Config::string('ai.conversations.tables.conversations', 'agent_conversations'),
            Config::string('ai.conversations.tables.messages', 'agent_conversation_messages'),
        ];
    }
};
