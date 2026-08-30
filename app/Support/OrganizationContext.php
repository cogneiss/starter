<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Organization;
use Closure;
use Spatie\Permission\PermissionRegistrar;

/**
 * The ambient organization for the current request, job or command. Registered
 * as a singleton, read by the BelongsToOrganization global scope.
 */
final class OrganizationContext
{
    private ?Organization $organization = null;

    /**
     * {@see runAs()} reached without resolving the singleton first.
     *
     * A queue worker keeps the container between jobs, so the organization the
     * last job bound is still bound when the next one starts. Every job that
     * touches organization-scoped models rebinds through this.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public static function run(Organization $organization, Closure $callback): mixed
    {
        return resolve(self::class)->runAs($organization, $callback);
    }

    public function set(Organization $organization): void
    {
        $this->organization = $organization;

        $this->syncPermissionsTeam();
    }

    public function get(): ?Organization
    {
        return $this->organization;
    }

    public function id(): ?string
    {
        return $this->organization?->id;
    }

    public function has(): bool
    {
        return $this->organization instanceof Organization;
    }

    public function forget(): void
    {
        $this->organization = null;

        $this->syncPermissionsTeam();
    }

    /**
     * Run a callback with a specific organization bound, restoring the previous
     * one afterwards even if the callback throws.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public function runAs(Organization $organization, Closure $callback): mixed
    {
        $previous = $this->organization;

        $this->organization = $organization;
        $this->syncPermissionsTeam();

        try {
            return $callback();
        } finally {
            $this->organization = $previous;
            $this->syncPermissionsTeam();
        }
    }

    /**
     * Roles and permissions are organization-scoped, so spatie's team id has to
     * follow the bound organization. Keeping it here means every entry point —
     * middleware, jobs, runAs — gets it without repeating itself.
     */
    private function syncPermissionsTeam(): void
    {
        resolve(PermissionRegistrar::class)->setPermissionsTeamId($this->organization?->id);
    }
}
