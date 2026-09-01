<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\MembershipStatus;
use App\Models\Activity;
use App\Models\ImpersonationLog;
use App\Models\LoginHistory;
use App\Support\OrganizationContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Wildcard eloquent listener that writes the audit trail. Models in
 * config('audit.models') and config('audit.extra') are audited on create,
 * update and delete;
 * ImpersonationLog and LoginHistory keep their own tables and bridge one
 * activity entry on create. Created and deleted entries store no attribute
 * values — only updates store a redacted before/after of what changed —
 * so a secret written at creation never reaches the audit table.
 */
final class RecordModelActivity
{
    /** @var list<class-string<Model>> */
    private const array BRIDGED = [ImpersonationLog::class, LoginHistory::class];

    /**
     * @param  array{0: Model}  $payload
     */
    public function handle(string $event, array $payload): void
    {
        $model = $payload[0];

        if ($model instanceof Activity) {
            return;
        }

        $action = str($event)->between('eloquent.', ':')->value();

        if (in_array($model::class, self::BRIDGED, true)) {
            if ($action === 'created') {
                $this->recordBridge($model);
            }

            return;
        }

        $audited = [...config()->array('audit.models'), ...config()->array('audit.extra')];

        if (! in_array($model::class, $audited, true)) {
            return;
        }

        $this->write($model, $action, $action === 'updated' ? $this->changes($model) : null);
    }

    private function recordBridge(Model $model): void
    {
        $organizationId = $model->getAttribute('organization_id')
            ?? resolve(OrganizationContext::class)->id();

        if ($organizationId === null && $model instanceof LoginHistory) {
            $organizationId = $model->user
                ?->memberships()
                ->where('status', MembershipStatus::Active)
                ->value('organization_id');
        }

        if (! is_string($organizationId)) {
            return;
        }

        $this->write($model, 'created', null, $organizationId);
    }

    /**
     * @param  array{before: array<string, mixed>, after: array<string, mixed>}|null  $changes
     */
    private function write(Model $model, string $action, ?array $changes, ?string $organizationId = null): void
    {
        $organizationId ??= resolve(OrganizationContext::class)->id()
            ?? $model->getAttribute('organization_id');

        if (! is_string($organizationId)) {
            return;
        }

        Activity::query()->create([
            'organization_id' => $organizationId,
            'log_name' => 'audit',
            'description' => $action.' '.class_basename($model),
            'subject_type' => $model->getMorphClass(),
            'subject_id' => $model->getKey(),
            'event' => $action,
            'causer_type' => Auth::user()?->getMorphClass(),
            'causer_id' => Auth::id(),
            'properties' => $changes,
        ]);
    }

    /**
     * Redacted before/after of the changed attributes only. Attribute names in
     * config('audit.redact') are omitted entirely — neither name nor value
     * reaches the stored payload.
     *
     * @return array{before: array<string, mixed>, after: array<string, mixed>}
     */
    private function changes(Model $model): array
    {
        $redacted = array_filter(config()->array('audit.redact'), is_string(...));

        $changed = collect($model->getChanges())
            ->except([$model->getUpdatedAtColumn() ?? 'updated_at', ...$redacted]);

        return [
            'before' => $changed->map(fn ($value, string $key) => $model->getOriginal($key))->all(),
            'after' => $changed->all(),
        ];
    }
}
