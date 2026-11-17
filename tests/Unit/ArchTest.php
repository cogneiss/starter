<?php

declare(strict_types=1);

use App\Actions\ConsumeConfirmToken;
use App\Actions\CreateOrganizationInvitation;
use App\Ai\Contracts\OrganizationScoped;
use App\Ai\Tools\ProposeAction;
use App\Mcp\Tools\ProposeChange;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Process;
use Laravel\Ai\Contracts\HasMiddleware;

arch()->preset()->php();
arch()->preset()->strict();
arch()->preset()->laravel();
arch()->preset()->security()->ignoring([
    'assert',
]);

// Tools read; they never write. ProposeAction and its MCP twin ProposeChange are
// the named exemptions, and the only thing they write is the proposal itself.
// Banning the facade and the confirm consumer was not enough: RememberFact once
// wrote memory through a class of its own. A writer is anything in App\Actions,
// App\Ai\Actions or App\Ai\Memory, so a tool may not reach for any of them.
foreach (['App\Ai\Tools', 'App\Mcp\Tools'] as $toolNamespace) {
    arch("{$toolNamespace} never use a writer")
        ->expect($toolNamespace)
        ->not->toUse([
            DB::class,
            ConsumeConfirmToken::class,
            'App\Actions',
            'App\Ai\Actions',
            'App\Ai\Memory',
        ])
        ->ignoring([ProposeAction::class, ProposeChange::class]);

    /**
     * The lethal trifecta: a tool already reads private data and sees untrusted
     * content, so it must never also hold a channel that sends data out.
     */
    arch("{$toolNamespace} never use an outbound channel")
        ->expect($toolNamespace)
        ->not->toUse([
            Http::class,
            'Illuminate\Http\Client',
            Mail::class,
            Notification::class,
            Process::class,
            'GuzzleHttp',
            'Symfony\Component\Process',
        ]);
}

// A class-level rule cannot see an Eloquent call, so the source of every tool is
// swept for the write verbs. AuthorizesToolCall is exempt: it records a refusal
// in the audit log, which is a log of the read, not a write of the record.
it('keeps every tool free of Eloquent writes', function (): void {
    $verbs = 'save|update|updateOrCreate|firstOrCreate|create|forceCreate|insert|upsert|delete|forceDelete|destroy|truncate|increment|decrement|touch|attach|detach|sync|fill|forceFill|remember';
    $exempt = ['ProposeAction.php', 'ProposeChange.php', 'AuthorizesToolCall.php'];

    $files = [
        ...glob(app_path('Ai/Tools').'/{,Concerns/}*.php', GLOB_BRACE) ?: [],
        ...glob(app_path('Mcp/Tools').'/{,Concerns/}*.php', GLOB_BRACE) ?: [],
    ];

    expect($files)->not->toBeEmpty();

    foreach ($files as $file) {
        if (in_array(basename($file), $exempt, true)) {
            continue;
        }

        expect(preg_match("/(->|::)({$verbs})\\(/", (string) file_get_contents($file), $hit))
            ->toBe(0, basename($file).' writes through ['.($hit[0] ?? '').']');
    }
});

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

// A controller is an endpoint, not a service: nothing else in the application
// may call one. The concerns namespace is the exception by definition — a trait
// exists to be used, and it is used by controllers only.
arch('controllers')
    ->expect('App\Http\Controllers')
    ->not->toBeUsed()
    ->ignoring('App\Http\Controllers\Concerns');

//
