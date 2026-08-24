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

arch('controllers')
    ->expect('App\Http\Controllers')
    ->not->toBeUsed();

//
