<?php

declare(strict_types=1);

use Tests\Fixtures\Resources\Incomplete\IncompleteResource;
use Tests\Fixtures\Resources\Valid\FixtureResource;

it('rejects a definition whose searchable column does not exist', function (): void {
    $defects = resourceSearchDefects(new IncompleteResource);

    expect($defects)->toContain("searchable() names 'nickname', which is not a column on users");
});

it('rejects a definition whose record label is blank', function (): void {
    $defects = resourceSearchDefects(new IncompleteResource);

    expect($defects)->toContain('recordLabel() is empty, so a hit would render as a blank row');
});

it('accepts a definition that names a real column and labels a record', function (): void {
    expect(resourceSearchDefects(new FixtureResource))->toBe([]);
});
