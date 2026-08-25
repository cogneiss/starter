<?php

declare(strict_types=1);

use function Laravel\Ai\agent;

/**
 * Grades the briefing prompt: the model is handed figures we counted, and the
 * briefing has to report those figures rather than invent nicer ones.
 */
$fixture = evalFixture('dashboard-briefing');

it('reports the figures it was handed and invents none', function (array $case) use ($fixture): void {
    $answer = agent(instructions: (string) file_get_contents(resource_path($fixture['prompt'])))
        ->prompt(json_encode($case['figures'], JSON_THROW_ON_ERROR), provider: $fixture['provider'], model: $fixture['model'])
        ->text;

    expect($answer)->not->toBeEmpty();

    foreach ($case['mentions'] as $figure) {
        expect($answer)->toContain($figure);
    }
})->with(evalCases('dashboard-briefing'));

it('stays short enough to sit on a dashboard', function (array $case) use ($fixture): void {
    $answer = agent(instructions: (string) file_get_contents(resource_path($fixture['prompt'])))
        ->prompt(json_encode($case['figures'], JSON_THROW_ON_ERROR), provider: $fixture['provider'], model: $fixture['model'])
        ->text;

    expect(mb_strlen($answer))->toBeGreaterThan(0)
        ->and(mb_strlen($answer))->toBeLessThan(400);
})->with(evalCases('dashboard-briefing'));
