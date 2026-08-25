<?php

declare(strict_types=1);

use App\Actions\ConsumeConfirmToken;
use App\Actions\CreateOrganizationInvitation;
use App\Ai\Contracts\OrganizationScoped;
use App\Ai\Tools\ProposeAction;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Contracts\HasMiddleware;

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
    ->not->toUse([DB::class, ConsumeConfirmToken::class])
    ->ignoring(ProposeAction::class);

// A vertical is an agent, and an agent is scoped to one organization and runs
// the default middleware. Writing is not on the list: an agent that reaches for
// the invitation model or a transaction has skipped the confirm gate.
arch('verticals are scoped to one organization')
    ->expect('App\Ai\Agents')
    ->toImplement(OrganizationScoped::class)
    ->ignoring('App\Ai\Agents\Concerns');

arch('verticals run the default middleware')
    ->expect('App\Ai\Agents')
    ->toImplement(HasMiddleware::class)
    ->ignoring('App\Ai\Agents\Concerns');

arch('verticals never write')
    ->expect('App\Ai\Agents')
    ->not->toUse([
        DB::class,
        ConsumeConfirmToken::class,
        CreateOrganizationInvitation::class,
    ]);

arch('controllers')
    ->expect('App\Http\Controllers')
    ->not->toBeUsed();

//
