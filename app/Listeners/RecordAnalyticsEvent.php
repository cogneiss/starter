<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Concerns\BelongsToOrganization;
use App\Contracts\AnalyticsReporter;
use App\Support\Analytics\Attributes\NoTrack;
use App\Support\Analytics\Attributes\Track;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use ReflectionClass;

/**
 * Wildcard eloquent listener that auto-tracks created and deleted for every
 * organization-scoped model — zero per-model wiring. #[Track] opts a model
 * into more events, #[NoTrack] opts it out entirely. Events carry the model
 * name, key and organization id only; an updated event adds the changed
 * attribute names, never a value.
 */
final class RecordAnalyticsEvent
{
    /**
     * @param  array{0: Model}  $payload
     */
    public function handle(string $event, array $payload): void
    {
        $model = $payload[0];

        if (! in_array(BelongsToOrganization::class, class_uses_recursive($model), true)) {
            return;
        }

        $reflection = new ReflectionClass($model);

        if ($reflection->getAttributes(NoTrack::class) !== []) {
            return;
        }

        $extra = ($reflection->getAttributes(Track::class)[0] ?? null)?->newInstance()->events ?? [];
        $action = str($event)->between('eloquent.', ':')->value();

        if (! in_array($action, ['created', 'deleted', ...$extra], true)) {
            return;
        }

        $key = $model->getKey();

        $properties = [
            'model' => class_basename($model),
            'id' => is_scalar($key) ? (string) $key : '',
            'organization_id' => $model->getAttribute('organization_id'),
        ];

        if ($action === 'updated') {
            $properties['changed'] = collect($model->getChanges())
                ->except($model->getUpdatedAtColumn() ?? 'updated_at')
                ->keys()
                ->all();
        }

        resolve(AnalyticsReporter::class)->track(
            Str::snake(class_basename($model)).'.'.$action,
            $properties,
        );
    }
}
