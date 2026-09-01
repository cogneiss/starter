<?php

declare(strict_types=1);

namespace App\Admin;

use App\Enums\FilterType;
use App\Models\Activity;
use App\Models\ApiToken;
use App\Models\FeatureOverride;
use App\Models\ImpersonationLog;
use App\Models\Organization;
use App\Models\RoleTemplate;
use App\Models\User;
use App\Models\WebhookEndpoint;
use App\Resources\ResourceColumn;
use App\Support\ResourceFilter;
use Illuminate\Database\Eloquent\Builder;

/**
 * The declared admin page set. This list is the single source the routes, the
 * navigation, and the admin tests all read — a page added here is gated,
 * listed, exported and audited, and a page not here does not exist.
 */
final class AdminResources
{
    /**
     * @return array<string, AdminResource>
     */
    public static function pages(): array
    {
        $pages = [
            new AdminResource(
                key: 'organizations',
                label: __('Organizations'),
                model: Organization::class,
                query: fn () => Organization::query(),
                searchable: ['name'],
                sortable: ['created_at', 'name'],
                filters: fn (): array => [
                    new ResourceFilter(key: 'when', label: __('Created'), type: FilterType::DateRange, column: 'created_at'),
                ],
                columns: [
                    new ResourceColumn(key: 'name', label: __('Name')),
                    new ResourceColumn(key: 'created_at', label: __('Created')),
                ],
            ),
            new AdminResource(
                key: 'users',
                label: __('Users'),
                model: User::class,
                query: fn () => User::query(),
                searchable: ['name', 'email'],
                sortable: ['created_at', 'name', 'email'],
                filters: fn (): array => [
                    new ResourceFilter(key: 'organization', label: __('Organization'), type: FilterType::MultiSelect, column: 'current_organization_id', options: self::organizationIds()),
                    new ResourceFilter(key: 'when', label: __('Created'), type: FilterType::DateRange, column: 'created_at'),
                ],
                columns: [
                    new ResourceColumn(key: 'name', label: __('Name')),
                    new ResourceColumn(key: 'email', label: __('Email')),
                    new ResourceColumn(key: 'current_organization_id', label: __('Organization')),
                    new ResourceColumn(key: 'created_at', label: __('Created')),
                ],
            ),
            new AdminResource(
                key: 'feature-overrides',
                label: __('Feature overrides'),
                model: FeatureOverride::class,
                query: fn () => FeatureOverride::query(),
                searchable: ['feature'],
                sortable: ['created_at', 'feature'],
                filters: fn (): array => [
                    new ResourceFilter(key: 'organization', label: __('Organization'), type: FilterType::MultiSelect, column: 'organization_id', options: self::organizationIds()),
                ],
                columns: [
                    new ResourceColumn(key: 'feature', label: __('Feature')),
                    new ResourceColumn(key: 'value', label: __('Value')),
                    new ResourceColumn(key: 'organization_id', label: __('Organization')),
                    new ResourceColumn(key: 'expires_at', label: __('Expires')),
                ],
            ),
            new AdminResource(
                key: 'role-templates',
                label: __('Role templates'),
                model: RoleTemplate::class,
                query: fn () => RoleTemplate::query(),
                searchable: ['name', 'description'],
                sortable: ['created_at', 'name'],
                filters: fn (): array => [
                    new ResourceFilter(key: 'when', label: __('Created'), type: FilterType::DateRange, column: 'created_at'),
                ],
                columns: [
                    new ResourceColumn(key: 'name', label: __('Name')),
                    new ResourceColumn(key: 'description', label: __('Description')),
                    new ResourceColumn(key: 'is_default', label: __('Default')),
                    new ResourceColumn(key: 'created_at', label: __('Created')),
                ],
            ),
            new AdminResource(
                key: 'impersonation-log',
                label: __('Impersonation log'),
                model: ImpersonationLog::class,
                query: fn () => ImpersonationLog::query(),
                searchable: ['impersonator.name', 'impersonated.name'],
                sortable: ['started_at', 'ended_at'],
                filters: fn (): array => [
                    new ResourceFilter(key: 'organization', label: __('Organization'), type: FilterType::MultiSelect, column: 'organization_id', options: self::organizationIds()),
                ],
                columns: [
                    new ResourceColumn(key: 'impersonator.name', label: __('Impersonator')),
                    new ResourceColumn(key: 'impersonated.name', label: __('Impersonated')),
                    new ResourceColumn(key: 'ip_address', label: __('IP address')),
                    new ResourceColumn(key: 'started_at', label: __('Started')),
                    new ResourceColumn(key: 'ended_at', label: __('Ended')),
                ],
            ),
            new AdminResource(
                key: 'audit-log',
                label: __('Audit log'),
                model: Activity::class,
                query: fn (): Builder => Activity::withoutOrganizationScope(),
                searchable: ['description'],
                sortable: ['created_at', 'event'],
                filters: fn (): array => [
                    new ResourceFilter(key: 'organization', label: __('Organization'), type: FilterType::MultiSelect, column: 'organization_id', options: self::organizationIds()),
                    new ResourceFilter(key: 'event', label: __('Event'), type: FilterType::MultiSelect, column: 'event', options: ['created', 'updated', 'deleted', 'role_changed', 'exported', 'viewed']),
                ],
                columns: [
                    new ResourceColumn(key: 'description', label: __('Description')),
                    new ResourceColumn(key: 'event', label: __('Event')),
                    new ResourceColumn(key: 'organization_id', label: __('Organization')),
                    new ResourceColumn(key: 'created_at', label: __('When')),
                ],
            ),
            new AdminResource(
                key: 'api-tokens',
                label: __('API tokens'),
                model: ApiToken::class,
                query: fn (): Builder => ApiToken::withoutOrganizationScope(),
                searchable: ['name'],
                sortable: ['created_at', 'name', 'last_used_at'],
                filters: fn (): array => [
                    new ResourceFilter(key: 'organization', label: __('Organization'), type: FilterType::MultiSelect, column: 'organization_id', options: self::organizationIds()),
                ],
                // Metadata only, on purpose: the plaintext is shown once at
                // creation and only its hash is stored, so no admin page has
                // anything secret to show.
                columns: [
                    new ResourceColumn(key: 'name', label: __('Name')),
                    new ResourceColumn(key: 'organization_id', label: __('Organization')),
                    new ResourceColumn(key: 'last_used_at', label: __('Last used')),
                    new ResourceColumn(key: 'expires_at', label: __('Expires')),
                    new ResourceColumn(key: 'created_at', label: __('Created')),
                ],
            ),
            new AdminResource(
                key: 'webhook-endpoints',
                label: __('Webhook endpoints'),
                model: WebhookEndpoint::class,
                query: fn (): Builder => WebhookEndpoint::withoutOrganizationScope(),
                searchable: ['url'],
                sortable: ['created_at', 'url', 'consecutive_failures'],
                filters: fn (): array => [
                    new ResourceFilter(key: 'organization', label: __('Organization'), type: FilterType::MultiSelect, column: 'organization_id', options: self::organizationIds()),
                    new ResourceFilter(key: 'active', label: __('Active'), type: FilterType::Boolean, column: 'active'),
                ],
                columns: [
                    new ResourceColumn(key: 'url', label: __('URL')),
                    new ResourceColumn(key: 'organization_id', label: __('Organization')),
                    new ResourceColumn(key: 'active', label: __('Active')),
                    new ResourceColumn(key: 'consecutive_failures', label: __('Consecutive failures')),
                    new ResourceColumn(key: 'created_at', label: __('Created')),
                ],
            ),
        ];

        $keyed = [];

        foreach ($pages as $page) {
            $keyed[$page->key()] = $page;
        }

        return $keyed;
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::pages());
    }

    /**
     * Filter options must equal stored column values, so organizations are
     * offered as ids.
     *
     * @return list<string>
     */
    private static function organizationIds(): array
    {
        /** @var list<string> */
        return Organization::query()->orderBy('id')->pluck('id')->all();
    }
}
