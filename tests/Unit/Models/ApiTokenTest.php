<?php

declare(strict_types=1);

use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Support\Str;

it('finds a token by id and plaintext hash', function (): void {
    $plain = Str::random(40);

    $token = ApiToken::factory()->create(['token' => hash('sha256', $plain)]);

    expect(ApiToken::findToken($token->id.'|'.$plain)?->is($token))->toBeTrue();
});

it('rejects a bearer without a pipe', function (): void {
    expect(ApiToken::findToken('no-pipe-here'))->toBeNull();
});

it('rejects a wrong plaintext for a real id', function (): void {
    $token = ApiToken::factory()->create();

    expect(ApiToken::findToken($token->id.'|'.Str::random(40)))->toBeNull();
});

it('rejects an unknown id', function (): void {
    expect(ApiToken::findToken(Str::uuid()->toString().'|'.Str::random(40)))->toBeNull();
});

it('belongs to the user who created it', function (): void {
    $user = User::factory()->create();

    $token = ApiToken::factory()->create(['created_by' => $user->id]);

    expect($token->creator?->is($user))->toBeTrue();
});
