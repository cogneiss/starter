<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\Activity;
use App\Models\User;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript('Activity')]
final class ActivityData extends Data
{
    public function __construct(
        public string $id,
        public string $description,
        public ?string $event,
        public ?string $subject_type,
        public ?string $subject_id,
        public ?string $causer,
        public string $created_at,
    ) {}

    public static function fromModel(Activity $activity): self
    {
        $causer = $activity->causer;

        return new self(
            id: (string) $activity->id,
            description: $activity->description,
            event: $activity->event,
            subject_type: $activity->subject_type === null ? null : class_basename($activity->subject_type),
            subject_id: $activity->subject_id === null ? null : (string) $activity->subject_id,
            causer: $causer instanceof User ? $causer->name : null,
            created_at: $activity->created_at?->toDateTimeString() ?? '',
        );
    }
}
