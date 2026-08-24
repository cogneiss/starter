<?php

declare(strict_types=1);

/**
 * A tool runs on behalf of a member, chosen by a model that read content we do
 * not control. Every tool therefore asks the policy first, through
 * App\Ai\Tools\Concerns\AuthorizesToolCall.
 *
 * @param  array<string, string>  $sources  label => PHP source
 * @return list<string>
 */
function toolsSkippingAuthorization(array $sources): array
{
    $violations = [];

    foreach ($sources as $label => $source) {
        if (str_contains($source, 'authorizeFor(')) {
            continue;
        }

        $violations[] = <<<TEXT
        {$label} never calls authorizeFor().

        Fix: use App\Ai\Tools\Concerns\AuthorizesToolCall and call
        \$this->authorizeFor(\$user, '<ability>', \$subject) before reading or proposing anything.
        TEXT;
    }

    return $violations;
}

it('fails the arch guard for a tool that skips authorizeFor', function (): void {
    $violations = toolsSkippingAuthorization([
        'UnguardedFixtureTool' => <<<'PHP'
        final class UnguardedFixtureTool implements Tool
        {
            public function handle(Request $request): string
            {
                return Organization::query()->findOrFail($request->get('id'))->name;
            }
        }
        PHP,
    ]);

    expect($violations)->toHaveCount(1)
        ->and($violations[0])->toContain('AuthorizesToolCall');
});

it('passes the arch guard for a tool that authorizes first', function (): void {
    expect(toolsSkippingAuthorization([
        'GuardedFixtureTool' => <<<'PHP'
        final class GuardedFixtureTool implements Tool
        {
            public function handle(Request $request): string
            {
                $this->authorizeFor($this->user, 'view', $this->organization);

                return $this->organization->name;
            }
        }
        PHP,
    ]))->toBe([]);
});

it('holds every first-party tool to the authorization arch guard', function (): void {
    $sources = [];

    foreach (glob(app_path('Ai/Tools').'/*.php') ?: [] as $file) {
        $sources[basename($file, '.php')] = (string) file_get_contents($file);
    }

    expect(toolsSkippingAuthorization($sources))->toBe([]);
})->group('arch');
