<?php

declare(strict_types=1);

namespace App\Ai\Middleware;

use App\Actions\DebitAiCredits;
use App\Ai\Contracts\OrganizationScoped;
use App\Enums\AiAuditStatus;
use App\Models\AiAuditLog;
use App\Support\AiPricing;
use App\Support\OrganizationContext;
use Closure;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Ai\Responses\StreamableAgentResponse;

/**
 * Last in the pipeline, so the row describes what actually went out rather than
 * what was asked for. The audit row and the charge for it are one transaction:
 * usage that is recorded but never billed, or billed but never recorded, is the
 * failure mode this exists to prevent.
 */
final readonly class RecordAudit
{
    public function __construct(
        private OrganizationContext $context,
        private DebitAiCredits $debits,
    ) {}

    public function handle(AgentPrompt $prompt, Closure $next): mixed
    {
        $agent = $prompt->agent;

        if (! $agent instanceof OrganizationScoped) {
            return $next($prompt);
        }

        $startedAt = hrtime(true);

        $response = $next($prompt);

        // A streamed response is metered when the stream completes, which is
        // the only point its usage is known. Anything else — a provider that
        // returned something this middleware cannot read — passes through
        // unmetered rather than being charged for a guess.
        if (! $response instanceof AgentResponse && ! $response instanceof StreamableAgentResponse) {
            return $response;
        }

        return $response->then(function (AgentResponse $response) use ($agent, $startedAt): void {
            $this->record($agent, $response, $startedAt);
        });
    }

    private function record(OrganizationScoped $agent, AgentResponse $response, int $startedAt): void
    {
        $usage = $response->usage;
        $costMicros = AiPricing::costMicros($response->meta->provider, $response->meta->model, $usage);

        DB::transaction(function () use ($agent, $response, $usage, $costMicros, $startedAt): void {
            $log = $this->context->runAs($agent->organization, fn (): AiAuditLog => AiAuditLog::query()->create([
                'user_id' => $agent->user->id,
                'agent' => $agent::class,
                'provider' => $response->meta->provider,
                'model' => $response->meta->model,
                'prompt_tokens' => $usage->promptTokens,
                'completion_tokens' => $usage->completionTokens,
                'total_tokens' => $usage->promptTokens + $usage->completionTokens,
                'cost_micros' => $costMicros,
                'duration_ms' => intdiv(hrtime(true) - $startedAt, 1_000_000),
                'status' => AiAuditStatus::Ok,
                'tool_calls' => $response->toolCalls->map(fn (ToolCall $call): string => $call->name)->all(),
            ]));

            if ($costMicros > 0) {
                $this->debits->handle($agent->organization, $costMicros, 'AI usage', $log);
            }
        });
    }
}
