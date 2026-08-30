<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\User;
use App\Onboarding\Checklist;
use App\Onboarding\StepRegistry;

it('gates nothing when the registry finds no step', function (): void {
    // The registry is pointed at a directory that holds no step files, so this
    // asks the one question that needs no data at all: with nothing to finish,
    // is the checklist already satisfied? It has to be, or an application that
    // registers no steps would redirect every person to onboarding forever.
    $checklist = new Checklist(new StepRegistry(directory: base_path('tests/Unit/Onboarding/steps')));

    expect($checklist->isSatisfied(new User, new Organization))->toBeTrue();
});
