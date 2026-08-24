<?php

declare(strict_types=1);

/**
 * An agent's instructions() is the one string the model is told to trust. Data
 * concatenated into it is indistinguishable from something we wrote, so it has
 * to arrive through App\Support\UntrustedContent::fence() instead.
 *
 * @param  array<string, string>  $sources  label => PHP source
 * @return list<string>
 */
function agentsInterpolatingIntoInstructions(array $sources): array
{
    $violations = [];

    foreach ($sources as $label => $source) {
        preg_match('/function instructions\(\).*?\n    \}/s', $source, $matches);

        $body = $matches[0] ?? '';

        if (! str_contains($body, '$') || str_contains($body, 'UntrustedContent::fence')) {
            continue;
        }

        $violations[] = <<<TEXT
        {$label} interpolates a value into instructions().

        Fix: wrap it in App\Support\UntrustedContent::fence(\$value, '<label>'), or move it
        into the prompt body, which App\Ai\Middleware\FenceUntrustedInput fences for you.
        TEXT;
    }

    return $violations;
}

it('rejects an agent that fails the instructions interpolation guard', function (): void {
    $violations = agentsInterpolatingIntoInstructions([
        'LeakyFixtureAgent' => <<<'PHP'
        final class LeakyFixtureAgent
        {
            public function instructions(): string
            {
                return 'You help '.$this->organization->name.' with support.';
            }
        }
        PHP,
    ]);

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toContain('UntrustedContent::fence');
});

it('accepts fenced and constant values in the instructions interpolation guard', function (): void {
    expect(agentsInterpolatingIntoInstructions([
        'FencedFixtureAgent' => <<<'PHP'
        final class FencedFixtureAgent
        {
            public function instructions(): string
            {
                return 'You help with support.'.UntrustedContent::fence($this->organization->name, 'org');
            }
        }
        PHP,
        'ConstantFixtureAgent' => <<<'PHP'
        final class ConstantFixtureAgent
        {
            public function instructions(): string
            {
                return 'You help with support.';
            }
        }
        PHP,
    ]))->toBe([]);
});

it('holds every first-party agent to the instructions interpolation guard', function (): void {
    $sources = [];

    foreach (glob(app_path('Ai/Agents').'/*.php') ?: [] as $file) {
        $sources[basename($file, '.php')] = (string) file_get_contents($file);
    }

    expect(agentsInterpolatingIntoInstructions($sources))->toBe([]);
})->group('arch');
