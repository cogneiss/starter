<?php

declare(strict_types=1);

arch()->preset()->php();
arch()->preset()->strict();
arch()->preset()->laravel();
arch()->preset()->security()->ignoring([
    'assert',
]);

// Tools read; they never write. ProposeAction is the one named exemption, and
// the only thing it writes is the proposal itself — see ResourceToolTest.
arch('tools')
    ->expect('App\Ai\Tools')
    ->not->toUse(['Illuminate\Support\Facades\DB', 'App\Actions\ConsumeConfirmToken'])
    ->ignoring('App\Ai\Tools\ProposeAction');

// A vertical is an agent, and an agent is scoped to one organization and runs
// the default middleware. Writing is not on the list: an agent that reaches for
// the invitation model or a transaction has skipped the confirm gate.
arch('verticals are scoped to one organization')
    ->expect('App\Ai\Agents')
    ->toImplement('App\Ai\Contracts\OrganizationScoped')
    ->ignoring('App\Ai\Agents\Concerns');

arch('verticals run the default middleware')
    ->expect('App\Ai\Agents')
    ->toImplement('Laravel\Ai\Contracts\HasMiddleware')
    ->ignoring('App\Ai\Agents\Concerns');

arch('verticals never write')
    ->expect('App\Ai\Agents')
    ->not->toUse([
        'Illuminate\Support\Facades\DB',
        'App\Actions\ConsumeConfirmToken',
        'App\Actions\CreateOrganizationInvitation',
    ]);

arch('controllers')
    ->expect('App\Http\Controllers')
    ->not->toBeUsed();

//
