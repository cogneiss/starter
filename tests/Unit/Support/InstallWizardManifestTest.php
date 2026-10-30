<?php

declare(strict_types=1);

use App\Support\Install\Pruner;

it('keeps the install manifest in step with the source', function (string $layer): void {
    $steps = new Pruner(base_path(), config()->array('install.layers'))->plan($layer);

    expect($steps)->not->toBeEmpty();
})->with(['ai', 'self']);

it('never lists the kit-wide spine for deletion', function (): void {
    $steps = new Pruner(base_path(), config()->array('install.layers'))->plan('ai');

    expect($steps)->not->toContain('delete app/Models/Organization.php')
        ->and($steps)->not->toContain('delete app/Models/User.php');
});
