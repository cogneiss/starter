<?php

declare(strict_types=1);

use App\Admin\AdminResources;
use App\Data\AdminRowData;
use App\Models\Organization;

it('declares every page over the same read-only resource contract', function (): void {
    $organization = Organization::factory()->create(['name' => 'Acme Rockets']);

    foreach (AdminResources::pages() as $key => $resource) {
        expect(class_exists($resource->model()))->toBeTrue()
            ->and($resource->dataClass())->toBe(AdminRowData::class)
            ->and($resource->policy())->toBeNull()
            ->and($resource->url($organization))->toBe(route('admin.pages', ['page' => $key]))
            ->and($resource->recordDescription($organization))->toBeNull();
    }

    expect(AdminResources::pages()['organizations']->recordLabel($organization))
        ->toBe('Acme Rockets');
});
