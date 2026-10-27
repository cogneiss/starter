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
use Laravel\Ai\Gateway\StepResponse;
use Laravel\Ai\Gateway\StepResult;
use Laravel\Ai\PendingStep;
use Laravel\Ai\Responses\Data\ToolCall;

/**
 * Last in the pipeline, so the row describes what actually went out rather than
 * what was asked for. The audit row and the charge for it are one transaction:
 * usage that is recorded but never billed, or billed but never recorded, is the
 * failure mode this exists to prevent.
 *
 * Middleware wraps every generation step and every step is a billed round trip,
 * so a run that calls tools writes one row per step.
 */
final readonly class RecordAudit
{
    public function __construct(
        private OrganizationContext $context,
        private DebitAiCredits $debits,
    ) {}

    public function handle(PendingStep $step, Closure $next): mixed
    {
        $agent = $step->options?->agent;

        if (! $agent instanceof OrganizationScoped) {
            return $next($step);
        }

        $startedAt = hrtime(true);

        $result = $next($step);

        // A streamed step is metered when the stream completes, which is the
        // only point its usage is known. Anything else — a step that was not
        // answered by a provider — passes through unmetered rather than being
        // charged for a guess.
        if (! $result instanceof StepResult) {
            return $result;
        }

        return $result->then(function (StepResponse $response) use ($agent, $startedAt): void {
            $this->record($agent, $response, $startedAt);
        });
    }

    private function record(OrganizationScoped $agent, StepResponse $response, int $startedAt): void
    {
        $usage = $response->usage;
        $costMicros = AiPricing::costMicros($response->meta->provider, $response->meta->model, $usage);

        DB::transaction(function () use ($agent, $response, $usage, $costMicros, $startedAt): void {
            $log = $this->context->runAs($agent->organization, fn (): AiAuditLog => AiAuditLog::query()->create([
                'user_id' => $agent->user->id,
                'agent' => $agent::class,
                'provider' => $response->meta->provider,
                'model' => $response->meta->model,
                'prompt_tokens' => $usage->inputTokens,
                'completion_tokens' => $usage->outputTokens,
                'total_tokens' => $usage->totalTokens(),
                'cost_micros' => $costMicros,
                'duration_ms' => intdiv(hrtime(true) - $startedAt, 1_000_000),
                'status' => AiAuditStatus::Ok,
                'tool_calls' => array_map(fn (ToolCall $call): string => $call->name, $response->toolCalls),
            ]));

            if ($costMicros > 0) {
                $this->debits->handle($agent->organization, $costMicros, 'AI usage', $log);
            }
        });
    }
}
