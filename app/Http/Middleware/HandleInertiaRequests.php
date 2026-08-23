<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\MembershipStatus;
use App\Models\Organization;
use App\Models\User;
use App\Support\Impersonation;
use App\Support\OrganizationContext;
use Illuminate\Http\Request;
use Inertia\Middleware;

final class HandleInertiaRequests extends Middleware
{
    /**
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
            ],
            'organization' => resolve(OrganizationContext::class)->get()?->only(['id', 'name', 'slug', 'personal']),
            'organizations' => $user instanceof User ? $this->organizationsFor($user) : [],
            'impersonating' => resolve(Impersonation::class)->impersonator()?->only(['id', 'name']),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * The organizations the user may switch to.
     *
     * @return array<int, array<string, mixed>>
     */
    private function organizationsFor(User $user): array
    {
        return $user->organizations()
            ->wherePivot('status', MembershipStatus::Active->value)
            ->orderBy('name')
            ->get()
            ->map(fn (Organization $organization): array => $organization->only(['id', 'name', 'slug', 'personal']))
            ->all();
    }
}
