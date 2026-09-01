<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Admin\AdminResource;
use App\Admin\AdminResources;
use App\Data\AdminRowData;
use App\Http\Controllers\Concerns\ListsResources;
use App\Models\Activity;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Resources\ResourceColumn;
use App\Support\ResourceQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Every admin page, one controller. The page set is `AdminResources::pages()`
 * — a key outside it is a 404 — and every view and every export writes an
 * audit entry naming the admin and the tenant it looked at, because a control
 * plane that reads across organizations without leaving a trace is a liability
 * rather than a feature.
 */
final readonly class AdminResourceController
{
    use ListsResources;

    public function index(Request $request, #[CurrentUser] User $user, string $page): Response|StreamedResponse
    {
        $resource = AdminResources::pages()[$page] ?? null;

        if ($resource === null) {
            abort(404);
        }

        if ($this->exportsCsv($request)) {
            $this->audit($user, $resource, $request, 'exported');

            return $this->exportResource($resource, $request);
        }

        $this->audit($user, $resource, $request, 'viewed');

        $columns = $resource->columns();

        return Inertia::render('admin/index', [
            'page' => $resource->key(),
            'label' => $resource->label(),
            'pages' => $this->navigation(),
            'columns' => $this->columnHeaders($resource, $columns),
            'list' => $this->listResource(
                $resource,
                $request,
                fn (Model $record): AdminRowData => AdminRowData::fromModel($record, $columns),
            ),
            'recentFailures' => $resource->key() === 'webhook-endpoints' ? $this->recentFailures() : null,
        ]);
    }

    /**
     * The audit entry a cross-organization read leaves behind. When the page is
     * narrowed to exactly one organization the entry belongs to that tenant's
     * ledger; an aggregate view is filed at the platform level instead.
     */
    private function audit(User $user, AdminResource $resource, Request $request, string $event): void
    {
        $tenant = $this->tenantIn($resource, $request);

        Activity::query()->create([
            'organization_id' => $tenant,
            'log_name' => 'admin',
            'description' => sprintf('%s admin %s for %s', $event, $resource->key(), $tenant ?? 'platform'),
            'event' => $event,
            'causer_type' => $user->getMorphClass(),
            'causer_id' => $user->id,
        ]);
    }

    private function tenantIn(AdminResource $resource, Request $request): ?string
    {
        $chosen = ResourceQuery::fromRequest($request, $resource)->filters['organization'] ?? null;

        if (is_array($chosen) && count($chosen) === 1 && is_string($chosen[0] ?? null)) {
            return $chosen[0];
        }

        return null;
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    private function navigation(): array
    {
        $pages = [];

        foreach (AdminResources::pages() as $page) {
            $pages[] = ['key' => $page->key(), 'label' => $page->label()];
        }

        return $pages;
    }

    /**
     * @param  list<ResourceColumn>  $columns
     * @return list<array{key: string, label: string, sortable: bool}>
     */
    private function columnHeaders(AdminResource $resource, array $columns): array
    {
        $headers = [];

        foreach ($columns as $column) {
            $headers[] = [
                'key' => $column->key,
                'label' => $column->label,
                'sortable' => in_array($column->key, $resource->sortable(), true),
            ];
        }

        return $headers;
    }

    /**
     * The latest failed deliveries across every organization, for the webhook
     * endpoints page's side panel.
     *
     * @return list<array{id: string, organization_id: string, status: string, attempt: int, created_at: string}>
     */
    private function recentFailures(): array
    {
        $failures = [];

        foreach (WebhookDelivery::withoutOrganizationScope()
            ->whereIn('status', ['failed', 'blocked'])
            ->latest()
            ->take(10)
            ->get() as $delivery) {
            $failures[] = [
                'id' => $delivery->id,
                'organization_id' => $delivery->organization_id,
                'status' => $delivery->status,
                'attempt' => $delivery->attempt,
                'created_at' => (string) $delivery->created_at,
            ];
        }

        return $failures;
    }
}
