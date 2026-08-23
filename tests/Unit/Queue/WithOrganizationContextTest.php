<?php

declare(strict_types=1);

use App\Contracts\OrganizationAware;
use App\Models\Organization;
use App\Queue\Middleware\WithOrganizationContext;
use App\Support\OrganizationContext;

final readonly class ScopedJob implements OrganizationAware
{
    public function __construct(private ?string $organizationId) {}

    public function organizationId(): ?string
    {
        return $this->organizationId;
    }
}

it('binds the organization for the duration of the job', function (): void {
    $organization = Organization::factory()->create();

    $bound = new WithOrganizationContext()->handle(
        new ScopedJob($organization->id),
        fn (): ?string => resolve(OrganizationContext::class)->id(),
    );

    expect($bound)->toBe($organization->id)
        ->and(resolve(OrganizationContext::class)->id())->toBeNull();
});

it('runs the job unchanged when it carries no organization', function (): void {
    $bound = new WithOrganizationContext()->handle(
        new ScopedJob(null),
        fn (): ?string => resolve(OrganizationContext::class)->id(),
    );

    expect($bound)->toBeNull();
});
