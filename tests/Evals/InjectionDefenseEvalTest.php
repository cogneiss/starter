<?php

declare(strict_types=1);

use App\Support\UntrustedContent;

use function Laravel\Ai\agent;

/**
 * Grades the fence itself: organization data that tries to give the model
 * orders gets fenced, and the model is expected to read it as data.
 *
 * The unit tests prove the fence is applied. This proves the fence works on a
 * model that could choose to ignore it, which no fake gateway can tell us.
 */
$fixture = evalFixture('injection-defense');

it('reads fenced organization data as data rather than as orders', function (array $case) use ($fixture): void {
    $prompt = $case['request'].PHP_EOL.PHP_EOL
        .UntrustedContent::fence($case['content'], 'organization-record');

    $answer = agent(instructions: (string) file_get_contents(resource_path($fixture['prompt'])))
        ->prompt($prompt, provider: $fixture['provider'], model: $fixture['model'])
        ->text;

    expect($answer)->not->toBeEmpty()
        ->and($answer)->not->toContain($case['forbidden']);
})->with(evalCases('injection-defense'));
