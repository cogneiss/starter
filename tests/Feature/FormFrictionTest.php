<?php

declare(strict_types=1);

use App\Actions\SeedOrganizationRoles;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * @param  array<string, mixed>  $overrides
 */
function registerAttempt(array $overrides = []): Illuminate\Testing\TestResponse
{
    return test()->post(route('register.store'), $overrides + [
        '_friction' => frictionToken(),
        'name' => 'Test User',
        'email' => 'friction@example.com',
        'password' => 'password1234',
        'password_confirmation' => 'password1234',
    ]);
}

beforeEach(function (): void {
    $this->freezeTime();
});

it('registers nobody when the honeypot is filled, while answering as success', function (): void {
    registerAttempt(['website' => 'https://spam.example'])
        ->assertRedirectToRoute('dashboard')
        ->assertSessionHasNoErrors();

    expect(User::query()->where('email', 'friction@example.com')->exists())->toBeFalse();
});

it('registers nobody when the token is missing or garbage', function (): void {
    registerAttempt(['_friction' => ''])->assertSessionHasNoErrors();
    registerAttempt(['_friction' => 'not-a-real-token'])->assertSessionHasNoErrors();

    expect(User::query()->where('email', 'friction@example.com')->exists())->toBeFalse();
});

it('rejects a submit one second under the minimum dwell and accepts the boundary', function (): void {
    registerAttempt(['_friction' => frictionToken(1), 'email' => 'fast@example.com']);
    expect(User::query()->where('email', 'fast@example.com')->exists())->toBeFalse();

    registerAttempt(['_friction' => frictionToken(2), 'email' => 'boundary@example.com']);
    expect(User::query()->where('email', 'boundary@example.com')->exists())->toBeTrue();

    auth()->logout();

    registerAttempt(['_friction' => frictionToken(3), 'email' => 'calm@example.com']);
    expect(User::query()->where('email', 'calm@example.com')->exists())->toBeTrue();
});

it('rejects a token one second past the maximum age and accepts the boundary', function (): void {
    registerAttempt(['_friction' => frictionToken(3600), 'email' => 'hour@example.com']);
    expect(User::query()->where('email', 'hour@example.com')->exists())->toBeTrue();

    auth()->logout();

    registerAttempt(['_friction' => frictionToken(3601), 'email' => 'stale@example.com']);
    expect(User::query()->where('email', 'stale@example.com')->exists())->toBeFalse();
});

it('sends no magic link on a tripped submit, while answering as success', function (): void {
    Notification::fake();

    User::factory()->create(['email' => 'target@example.com']);

    $this->from(route('magic-link.create'))
        ->post(route('magic-link.store'), [
            '_friction' => frictionToken(),
            'website' => 'filled',
            'email' => 'target@example.com',
        ])
        ->assertRedirect(route('magic-link.create'))
        ->assertSessionHas('status', 'A login link will be sent if the account exists.');

    Notification::assertNothingSent();
});

it('accepts no invitation on a tripped submit, while answering as success', function (): void {
    $organization = Organization::factory()->create();
    resolve(SeedOrganizationRoles::class)->handle($organization);

    $invitation = OrganizationInvitation::factory()->create([
        'organization_id' => $organization->id,
        'email' => 'invited@example.com',
        'role' => 'Member',
        'token' => hash('sha256', 'raw-token'),
    ]);

    $user = User::factory()->create(['email' => 'invited@example.com']);

    $this->actingAs($user)
        ->post(route('organization-invitation-acceptance.update', ['token' => 'raw-token']), [
            '_friction' => frictionToken(),
            'website' => 'filled',
        ])
        ->assertRedirectToRoute('dashboard');

    expect($invitation->refresh()->accepted_at)->toBeNull();
});

it('logs the trip without any of the submitted values', function (): void {
    Log::spy();

    registerAttempt([
        'website' => 'https://spam.example',
        'email' => 'canary-pii@example.com',
        'name' => 'Canary Name',
    ]);

    Log::shouldHaveReceived('warning')->once()->withArgs(function (string $message, array $context): bool {
        $logged = $message.json_encode($context);

        return $context['reason'] === 'honeypot'
            && $context['route'] === 'register.store'
            && ! str_contains($logged, 'canary-pii@example.com')
            && ! str_contains($logged, 'Canary Name')
            && ! str_contains($logged, 'spam.example');
    });
});

it('lets a precognitive request validate instead of answering with the decoy', function (): void {
    $this->withHeaders(['Precognition' => 'true', 'Precognition-Validate-Only' => 'email'])
        ->postJson(route('register.store'), [
            'website' => 'filled',
            'email' => 'not-an-email',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});
