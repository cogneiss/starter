<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Data\ImpersonatorData;
use App\Data\NotificationData;
use App\Data\OrganizationData;
use App\Data\UserData;
use App\Enums\MembershipStatus;
use App\Models\Organization;
use App\Models\User;
use App\Support\Impersonation;
use App\Support\OrganizationContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Lang;
use Inertia\Middleware;

final class HandleInertiaRequests extends Middleware
{
    /**
     * How many unread notifications the panel shows without being opened.
     */
    private const int RECENT_NOTIFICATIONS = 5;

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

        $unread = $user instanceof User && $organization instanceof Organization
            ? $this->unreadForOrganization($user, $organization)
            : null;

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user instanceof User ? UserData::fromModel($user) : null,
            ],
            'organization' => $organization instanceof Organization ? OrganizationData::fromModel($organization) : null,
            'organizations' => $user instanceof User ? $this->organizationsFor($user) : [],
            'impersonating' => $impersonator instanceof User ? ImpersonatorData::fromModel($impersonator) : null,
            'locale' => app()->getLocale(),
            'supportedLocales' => config()->array('app.supported_locales'),
            'translations' => $this->translations(),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'unreadNotifications' => $unread instanceof Builder ? $unread->count() : 0,
            'recentNotifications' => $unread instanceof Builder ? $this->recent($unread) : [],
        ];
    }

    /**
     * The user's unread notifications inside one organization.
     *
     * The tenant is a where clause on the query, not a check on the rows that
     * come back: a person who belongs to two organizations must not carry the
     * first one's unread count into the second, and a count is not something
     * you can filter after the fact.
     *
     * @return Builder<DatabaseNotification>
     */
    private function unreadForOrganization(User $user, Organization $organization): Builder
    {
        return $user->unreadNotifications()
            ->getQuery()
            ->where('organization_id', $organization->id);
    }

    /**
     * The newest few of them, as the panel renders them.
     *
     * @param  Builder<DatabaseNotification>  $unread
     * @return array<int, NotificationData>
     */
    private function recent(Builder $unread): array
    {
        return $unread->clone()
            ->latest()
            ->take(self::RECENT_NOTIFICATIONS)
            ->get()
            ->map(fn (DatabaseNotification $notification): NotificationData => NotificationData::fromModel($notification))
            ->all();
    }

    /**
     * The active locale's interface strings, flattened to the dotted keys the
     * client asks for.
     *
     * The PHP files stay the single source. A locale with no file of its own
     * ships an empty map rather than another locale's words, so a missing
     * translation shows as its key on the screen instead of quietly reading
     * back in English.
     *
     * @return array<string, string>
     */
    private function translations(): array
    {
        // The loader rather than `Lang::get()`: `get()` reads the fallback
        // locale's file when the active one has no answer, which is exactly the
        // quiet English that must not reach the screen.
        $messages = Lang::getLoader()->load(app()->getLocale(), 'ui');

        /** @var array<string, string> $flattened */
        $flattened = Arr::dot($messages);

        return $flattened;
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
