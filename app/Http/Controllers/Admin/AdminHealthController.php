<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Admin\AdminResources;
use App\Models\Activity;
use App\Models\User;
use App\Support\Health\HealthReport;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The control plane's front page: the same health checks `app:doctor` and the
 * `/up` endpoint run, resolved from the same container binding, so the panel
 * cannot drift from what the operators' tooling actually measures.
 */
final readonly class AdminHealthController
{
    public function __invoke(#[CurrentUser] User $user, HealthReport $report): Response
    {
        Activity::query()->create([
            'organization_id' => null,
            'log_name' => 'admin',
            'description' => 'viewed admin health for platform',
            'event' => 'viewed',
            'causer_type' => $user->getMorphClass(),
            'causer_id' => $user->id,
        ]);

        $pages = [];

        foreach (AdminResources::pages() as $page) {
            $pages[] = ['key' => $page->key(), 'label' => $page->label()];
        }

        return Inertia::render('admin/health', [
            'report' => $report->run(),
            'pages' => $pages,
        ]);
    }
}
