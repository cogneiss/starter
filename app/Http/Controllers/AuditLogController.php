<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\ActivityData;
use App\Http\Controllers\Concerns\ListsResources;
use App\Models\Activity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class AuditLogController
{
    use ListsResources;

    public function index(Request $request): Response|StreamedResponse
    {
        Gate::authorize('viewAny', Activity::class);

        if ($this->exportsCsv($request)) {
            return $this->exportResource('audit-log', $request);
        }

        return Inertia::render('audit/index', [
            'entries' => $this->listResource(
                'audit-log',
                $request,
                $this->entryRow(...),
                fn (Builder $query): Builder => $query->with('causer'),
            ),
        ]);
    }

    private function entryRow(Model $record): ActivityData
    {
        assert($record instanceof Activity);

        return ActivityData::fromModel($record);
    }
}
