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
use Illuminate\Support\Str;
use Laravel\Ai\Gateway\StepResponse;
use Laravel\Ai\Gateway\StepResult;
use Laravel\Ai\PendingStep;
use Laravel\Ai\Responses\Data\ToolCall;
use Throwable;

/**
 * Last in the pipeline, so the row describes what actually went out rather than
 * what was asked for. The audit row and the charge for it are one transaction:
 * usage that is recorded but never billed, or billed but never recorded, is the
 * failure mode this exists to prevent.
 *
 * Middleware wraps every generation step and every step is a billed round trip,
 * so a run that calls tools writes one row per step. The rows of one run share
 * a run id, which is the SDK's invocation id, and quota counts runs.
 */
final readonly class RecordAudit
{
    private const int VALUE_LIMIT = 200;

    private const int ARGUMENTS_LIMIT = 4000;

    private const int ERROR_LIMIT = 500;

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

        $runId = $step->invocationId ?? (string) Str::uuid();

        try {
            $result = $next($step);
        } catch (Throwable $throwable) {
            $this->recordError($agent, $step, $runId, $throwable, $startedAt);

            throw $throwable;
        }

        // A streamed step is metered when the stream completes, which is the
        // only point its usage is known. Anything else — a step that was not
        // answered by a provider — passes through unmetered rather than being
        // charged for a guess.
        if (! $result instanceof StepResult) {
            return $result;
        }

        return $result->then(function (StepResponse $response) use ($agent, $runId, $startedAt): void {
            $this->record($agent, $response, $runId, $startedAt);
        });
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return array<array-key, mixed>
     */
    private static function scrub(array $values): array
    {
        $redacted = array_filter(config()->array('audit.redact'), is_string(...));
        $out = [];

        foreach ($values as $key => $value) {
            $out[$key] = match (true) {
                is_string($key) && (in_array($key, $redacted, true) || preg_match('/key|token|secret|password|authorization/i', $key) === 1) => '[redacted]',
                is_array($value) => self::scrub($value),
                is_string($value) => Str::limit($value, self::VALUE_LIMIT, ''),
                default => $value,
            };
        }

        return $out;
    }

    /**
     * Arguments are model-written, so they are redacted by key name and capped:
     * a long string is cut, and a payload still over the budget is replaced by
     * a marker rather than stored whole.
     *
     * @param  array<ToolCall>  $calls
     * @return list<array{name: string, arguments: array<array-key, mixed>}>|null
     */
    private function toolArguments(array $calls): ?array
    {
        if ($calls === []) {
            return null;
        }

        $recorded = array_map(fn (ToolCall $call): array => [
            'name' => $call->name,
            'arguments' => self::scrub($call->arguments),
        ], array_values($calls));

        if (mb_strlen(json_encode($recorded, JSON_THROW_ON_ERROR)) > self::ARGUMENTS_LIMIT) {
            return array_map(fn (array $call): array => ['name' => $call['name'], 'arguments' => ['truncated' => true]], $recorded);
        }

        return $recorded;
    }

    private function record(OrganizationScoped $agent, StepResponse $response, string $runId, int $startedAt): void
    {
        $usage = $response->usage;
        $costMicros = AiPricing::costMicros($response->meta->provider, $response->meta->model, $usage);

        DB::transaction(function () use ($agent, $response, $usage, $costMicros, $runId, $startedAt): void {
            $log = $this->context->runAs($agent->organization, fn (): AiAuditLog => AiAuditLog::query()->create([
                'user_id' => $agent->user->id,
                'run_id' => $runId,
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
                'tool_arguments' => $this->toolArguments($response->toolCalls),
            ]));

            if ($costMicros > 0) {
                $this->debits->handle($agent->organization, $costMicros, 'AI usage', $log);
            }
        });
    }

    /**
     * A step that threw wrote nothing through the success path, so the failure
     * is recorded here and rethrown.
     */
    private function recordError(OrganizationScoped $agent, PendingStep $step, string $runId, Throwable $exception, int $startedAt): void
    {
        try {
            $this->context->runAs($agent->organization, fn (): AiAuditLog => AiAuditLog::query()->create([
                'user_id' => $agent->user->id,
                'run_id' => $runId,
                'agent' => $agent::class,
                'provider' => $step->provider,
                'model' => $step->model,
                'duration_ms' => intdiv(hrtime(true) - $startedAt, 1_000_000),
                'status' => AiAuditStatus::Error,
                'error' => Str::limit($exception::class.': '.$exception->getMessage(), self::ERROR_LIMIT, ''),
            ]));
        } catch (Throwable) {
            // The caller must see the provider's exception, so a failed audit insert is dropped rather than thrown over it.
        }
    }
}
