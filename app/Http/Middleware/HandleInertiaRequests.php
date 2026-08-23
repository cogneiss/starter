<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Data\ImpersonatorData;
use App\Data\OrganizationData;
use App\Data\UserData;
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

        $organization = resolve(OrganizationContext::class)->get();
        $impersonator = resolve(Impersonation::class)->impersonator();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user instanceof User ? UserData::fromModel($user) : null,
            ],
            'organization' => $organization instanceof Organization ? OrganizationData::fromModel($organization) : null,
            'organizations' => $user instanceof User ? $this->organizationsFor($user) : [],
            'impersonating' => $impersonator instanceof User ? ImpersonatorData::fromModel($impersonator) : null,
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * The organizations the user may switch to.
     *
     * @return array<int, OrganizationData>
     */
    private function organizationsFor(User $user): array
    {
        return $user->organizations()
            ->wherePivot('status', MembershipStatus::Active->value)
            ->orderBy('name')
            ->get()
            ->map(fn (Organization $organization): OrganizationData => OrganizationData::fromModel($organization))
            ->all();
    }
}
