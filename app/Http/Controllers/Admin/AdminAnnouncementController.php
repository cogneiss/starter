<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\SaveAnnouncement;
use App\Enums\AnnouncementLevel;
use App\Http\Requests\SaveAnnouncementRequest;
use App\Models\Announcement;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The write side of the announcements admin page. Reaching it already took the
 * platform gate, so there is nothing further to authorize: without the ability
 * these routes are a 404 like the rest of `/admin`.
 */
final readonly class AdminAnnouncementController
{
    public function create(): Response
    {
        return $this->form(null);
    }

    public function edit(Announcement $announcement): Response
    {
        return $this->form($announcement);
    }

    public function store(SaveAnnouncementRequest $request, SaveAnnouncement $action): RedirectResponse
    {
        $action->handle(null, $request->validated());

        return $this->saved(__('Announcement created.'));
    }

    public function update(SaveAnnouncementRequest $request, Announcement $announcement, SaveAnnouncement $action): RedirectResponse
    {
        $action->handle($announcement, $request->validated());

        return $this->saved(__('Announcement updated.'));
    }

    public function destroy(Announcement $announcement): RedirectResponse
    {
        $announcement->delete();

        return $this->saved(__('Announcement deleted.'));
    }

    private function form(?Announcement $announcement): Response
    {
        return Inertia::render('admin/announcement', [
            'announcement' => $announcement instanceof Announcement ? [
                'id' => $announcement->id,
                'title' => $announcement->title,
                'body' => $announcement->body,
                'level' => $announcement->level->value,
                'starts_at' => $announcement->starts_at->format('Y-m-d\TH:i'),
                'ends_at' => $announcement->ends_at?->format('Y-m-d\TH:i'),
                'organization_id' => $announcement->organization_id,
            ] : null,
            'levels' => array_column(AnnouncementLevel::cases(), 'value'),
            'organizations' => Organization::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    private function saved(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return to_route('admin.pages', ['page' => 'announcements']);
    }
}
