<?php

declare(strict_types=1);

use function Laravel\Ai\agent;

/**
 * Grades the invitation drafter's prompt against a real model.
 *
 * Nothing here pins the model's wording: a draft is right if it names the
 * address that was asked for, names a role, and does not claim to have sent
 * anything. Those are properties of a correct answer; the sentence around them
 * is the model's business and changes with every release.
 */
$fixture = evalFixture('invitation-drafter');

it('names the address and a role the request asked for', function (array $case) use ($fixture): void {
    $answer = agent(instructions: (string) file_get_contents(resource_path($fixture['prompt'])))
        ->prompt($case['request'], provider: $fixture['provider'], model: $fixture['model'])
        ->text;

    expect($answer)->not->toBeEmpty()
        ->and($answer)->toContain($case['email'])
        ->and(mb_strtolower($answer))->toContain($case['role']);
})->with(evalCases('invitation-drafter'));

it('never claims the invitation already happened', function (array $case) use ($fixture): void {
    $answer = mb_strtolower(agent(instructions: (string) file_get_contents(resource_path($fixture['prompt'])))
        ->prompt($case['request'], provider: $fixture['provider'], model: $fixture['model'])
        ->text);

    expect($answer)->not->toContain('has been sent')
        ->and($answer)->not->toContain('i have sent')
        ->and($answer)->not->toContain('invitation sent');
})->with(evalCases('invitation-drafter'));
