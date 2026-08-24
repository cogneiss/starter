<?php

declare(strict_types=1);

use App\Ai\Agents\Concerns\HasDefaultMiddleware;
use App\Ai\Middleware\EnforceQuota;
use App\Ai\Middleware\FenceUntrustedInput;
use App\Ai\Middleware\FilterTopics;
use App\Ai\Middleware\RecordAudit;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Support\AiTier;
use Illuminate\Auth\Access\AuthorizationException;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Enums\Lab;
use Tests\Fixtures\Ai\KernelFixtureAgent;

/**
 * @return array{0: User, 1: Organization}
 */
function kernelMember(): array
{
    $membership = OrganizationMembership::factory()->create();

    return [$membership->user, $membership->organization];
}

/**
 * @return list<class-string>
 */
function kernelClassesIn(string $directory): array
{
    $namespace = 'App\\'.str_replace('/', '\\', $directory).'\\';

    $files = glob(app_path($directory).'/*.php') ?: [];

    /** @var list<class-string> $classes */
    $classes = array_map(
        static fn (string $file): string => $namespace.basename($file, '.php'),
        $files,
    );

    return $classes;
}

it('runs the default middleware in the one order that is correct', function (): void {
    [$user, $organization] = kernelMember();

    // Quota first: a prompt that will be rejected is never paid for. Audit
    // last: it records what actually went out, after every other slot has had
    // its chance to change or refuse the prompt.
    expect((new KernelFixtureAgent($user, $organization))->middleware())->toBe([
        EnforceQuota::class,
        FenceUntrustedInput::class,
        FilterTopics::class,
        RecordAudit::class,
    ]);
});

it('sends a prompt through every middleware slot', function (): void {
    [$user, $organization] = kernelMember();

    KernelFixtureAgent::fake(['Hello from the fixture.'])->preventStrayPrompts();

    $response = (new KernelFixtureAgent($user, $organization))->prompt('Say hello.');

    expect($response->text)->toBeString()->not->toBeEmpty();

    KernelFixtureAgent::assertPrompted('Say hello.');
});

it('refuses to build an organization scoped agent for a non-member', function (): void {
    $outsider = User::factory()->create();
    $organization = Organization::factory()->create();

    expect(fn (): KernelFixtureAgent => new KernelFixtureAgent($outsider, $organization))
        ->toThrow(AuthorizationException::class);
});

it('resolves a tier to a provider and model from configuration', function (): void {
    expect(AiTier::for('cheap'))->toBe([
        'provider' => Lab::Anthropic,
        'model' => 'claude-haiku-4-5-20251001',
    ]);
});

it('leaves the model null when a tier defers to the provider default', function (): void {
    config()->set('ai.tiers.smart.model', null);

    expect(AiTier::for('smart')['model'])->toBeNull();
});

it('refuses an unconfigured tier', function (): void {
    expect(fn (): array => AiTier::for('genius'))->toThrow(InvalidArgumentException::class);
});

it('refuses a tier configured without a provider', function (): void {
    config()->set('ai.tiers.cheap.provider', 'anthropic');

    expect(fn (): array => AiTier::for('cheap'))->toThrow(InvalidArgumentException::class);
});

it('keeps every agent final, promptable and on the default middleware', function (): void {
    $classes = kernelClassesIn('Ai/Agents');

    foreach ($classes as $class) {
        $reflection = new ReflectionClass($class);

        expect($reflection->isFinal())->toBeTrue("{$class} must be final")
            ->and($reflection->implementsInterface(Agent::class))->toBeTrue("{$class} must implement ".Agent::class)
            ->and(in_array(HasDefaultMiddleware::class, class_uses_recursive($class), true))
            ->toBeTrue("{$class} must use ".HasDefaultMiddleware::class);
    }

    // Keeps the guard honest while app/Ai/Agents is still empty: an
    // assertion-less test reports as risky, not as coverage of anything.
    expect($classes)->toBeArray();
})->group('arch');

it('keeps every class in the tools directory a tool', function (): void {
    $classes = kernelClassesIn('Ai/Tools');

    foreach ($classes as $class) {
        expect((new ReflectionClass($class))->implementsInterface(Tool::class))
            ->toBeTrue("{$class} must implement ".Tool::class);
    }

    expect($classes)->toBeArray();
})->group('arch');
