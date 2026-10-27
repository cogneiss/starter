<?php

declare(strict_types=1);

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Ai\Enums\MessageStatus;
use Laravel\Ai\Migrations\AiMigration;

return new class extends AiMigration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $table = Config::string('ai.conversations.tables.messages', 'agent_conversation_messages');

        Schema::connection($this->getConnection())->table($table, function (Blueprint $blueprint): void {
            $blueprint->longText('steps')->nullable();
            $blueprint->string('status', 25)->default(MessageStatus::Completed->value);
        });

        $this->query($table)->where('role', 'user')->update(['steps' => '[]']);

        $this->query($table)
            ->select('conversation_id')
            ->distinct()
            ->orderBy('conversation_id')
            ->chunk(100, function (Collection $conversations) use ($table): void {
                foreach ($conversations as $conversation) {
                    if (is_string($conversation->conversation_id)) {
                        $this->backfill($table, $conversation->conversation_id);
                    }
                }
            });

        Schema::connection($this->getConnection())->table($table, function (Blueprint $blueprint): void {
            $blueprint->longText('steps')->nullable(false)->change();
            $blueprint->dropColumn(['tool_calls', 'tool_results', 'approval_state']);
            $blueprint->dropIndex('participant_index');
            $blueprint->index(['participant_type', 'participant_id', 'agent'], 'participant_index');
        });
    }

    /**
     * Rewrite one conversation's assistant rows as steps, each result landing on the call that made it.
     */
    protected function backfill(string $table, string $conversationId): void
    {
        $rows = $this->query($table)
            ->where('conversation_id', $conversationId)
            ->where('role', 'assistant')
            ->orderBy('id')
            ->get();

        // A result was recorded on the row of the request that produced it, which may be a later row than its call...
        $results = [];

        foreach ($rows as $row) {
            foreach ($this->decoded($row->tool_results) as $result) {
                if (is_array($result) && is_string($result['id'] ?? null)) {
                    $results[$result['id']] = $result;
                }
            }
        }

        foreach ($rows as $row) {
            $meta = $this->decoded($row->meta);
            $calls = $this->callsWithResults($this->decoded($row->tool_calls), $results);

            $content = is_string($row->content) ? $row->content : '';
            $reasoning = is_string($meta['reasoning'] ?? null) ? $meta['reasoning'] : '';

            $steps = $calls !== [] && $content !== ''
                ? [$this->step('', $calls), $this->step($content, [], $reasoning)]
                : [$this->step($content, $calls, $reasoning)];

            unset($meta['provider_steps'], $meta['provider_content_blocks'], $meta['reasoning']);

            $this->query($table)->where('id', $row->id)->update([
                'steps' => json_encode($steps),
                'meta' => json_encode($meta),
            ]);
        }
    }

    /**
     * Attach each call's recorded result, dropping calls that never got one.
     *
     * @param  array<mixed>  $calls
     * @param  array<string, array<mixed>>  $results
     * @return list<array<mixed>>
     */
    protected function callsWithResults(array $calls, array $results): array
    {
        $paired = [];

        foreach ($calls as $call) {
            if (! is_array($call) || ! is_string($call['id'] ?? null) || ! isset($results[$call['id']])) {
                continue;
            }

            $result = $results[$call['id']];

            $paired[] = [
                ...$call,
                'result' => $result['result'] ?? null,
                ...array_filter([
                    'denied' => $result['denied'] ?? false,
                    'failed' => $result['failed'] ?? false,
                ], static fn (mixed $flag): bool => (bool) $flag),
            ];
        }

        return $paired;
    }

    /**
     * Build a conversation step from its content, tool calls, and reasoning.
     *
     * @param  list<array<mixed>>  $calls
     * @return array<string, mixed>
     */
    protected function step(string $content, array $calls = [], string $reasoning = ''): array
    {
        return [
            'content' => $content,
            'tool_calls' => $calls,
            'reasoning' => $reasoning,
            'replay_blocks' => [],
            'provider_tool_calls' => [],
        ];
    }

    /**
     * Decode a JSON value into an array, returning an empty array for invalid or empty input.
     *
     * @return array<mixed>
     */
    protected function decoded(mixed $json): array
    {
        return is_string($json) && is_array($decoded = json_decode($json, true)) ? $decoded : [];
    }

    /**
     * Create a query builder for the conversation messages table.
     */
    protected function query(string $table): Builder
    {
        return DB::connection($this->getConnection())->table($table);
    }
};
