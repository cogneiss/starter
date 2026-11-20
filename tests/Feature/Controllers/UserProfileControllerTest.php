<?php

declare(strict_types=1);

use App\Models\User;

it('renders profile edit page', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->fromRoute('dashboard')
        ->get(route('user-profile.edit'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('user-profile/edit')
            ->has('status'));
});

it('may update profile information', function (): void {
    $user = User::factory()->create([
        'name' => 'Old Name',
        'email' => 'old@example.com',
    ]);

    $response = $this->actingAs($user)
        ->fromRoute('user-profile.edit')
        ->patch(route('user-profile.update'), [
            'name' => 'New Name',
            'email' => 'new@example.com',
        ]);

    $response->assertRedirectToRoute('user-profile.edit')
        ->assertInertiaFlash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

    expect($user->refresh()->name)->toBe('New Name')
        ->and($user->email)->toBe('new@example.com');
});

it('resets email verification when email changes', function (): void {
    $user = User::factory()->create([
        'email' => 'old@example.com',
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($user)
        ->fromRoute('user-profile.edit')
        ->patch(route('user-profile.update'), [
            'name' => $user->name,
            'email' => 'new@example.com',
        ]);

    $response->assertRedirectToRoute('user-profile.edit');

    expect($user->refresh()->email_verified_at)->toBeNull();
});

it('keeps email verification when email stays the same', function (): void {
    $verifiedAt = now();

    $user = User::factory()->create([
        'email' => 'same@example.com',
        'email_verified_at' => $verifiedAt,
    ]);

    $response = $this->actingAs($user)
        ->fromRoute('user-profile.edit')
        ->patch(route('user-profile.update'), [
            'name' => 'New Name',
            'email' => 'same@example.com',
        ]);

    $response->assertRedirectToRoute('user-profile.edit');

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

it('requires name', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->fromRoute('user-profile.edit')
        ->patch(route('user-profile.update'), [
            'email' => 'test@example.com',
        ]);

    $response->assertRedirectToRoute('user-profile.edit')
        ->assertSessionHasErrors('name');
});

it('requires email', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->fromRoute('user-profile.edit')
        ->patch(route('user-profile.update'), [
            'name' => 'Test User',
        ]);

    $response->assertRedirectToRoute('user-profile.edit')
        ->assertSessionHasErrors('email');
});

it('requires valid email', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->fromRoute('user-profile.edit')
        ->patch(route('user-profile.update'), [
            'name' => 'Test User',
            'email' => 'not-an-email',
        ]);

    $response->assertRedirectToRoute('user-profile.edit')
        ->assertSessionHasErrors('email');
});

it('refuses a profile address the sign-up form would refuse', function (): void {
    $user = User::factory()->create(['email' => 'ada@example.com']);

    $response = $this->actingAs($user)
        ->fromRoute('user-profile.edit')
        ->patch(route('user-profile.update'), [
            'name' => 'Ada Lovelace',
            'email' => 'ada@localhost',
        ]);

    // The framework `email` rule accepts a host with no dot, so the message
    // of `ValidEmail` is what shows that rule is the one answering.
    $response->assertRedirectToRoute('user-profile.edit')
        ->assertSessionHasErrors(['email' => 'The email must be a valid email address.']);

    expect($user->refresh()->email)->toBe('ada@example.com');
});

it('saves a new name beside a stored address the sign-up form would refuse', function (): void {
    $user = User::factory()->create(['email' => 'ada@localhost']);

    $response = $this->actingAs($user)
        ->fromRoute('user-profile.edit')
        ->patch(route('user-profile.update'), [
            'name' => 'Ada Lovelace',
            'email' => 'ada@localhost',
        ]);

    $response->assertSessionHasNoErrors();

    expect($user->refresh()->name)->toBe('Ada Lovelace');
});

it('requires unique email except own', function (): void {
    $existingUser = User::factory()->create(['email' => 'existing@example.com']);
    $user = User::factory()->create(['email' => 'test@example.com']);

    $response = $this->actingAs($user)
        ->fromRoute('user-profile.edit')
        ->patch(route('user-profile.update'), [
            'name' => 'Test User',
            'email' => 'existing@example.com',
        ]);

    $response->assertRedirectToRoute('user-profile.edit')
        ->assertSessionHasErrors('email');
});

it('allows keeping same email', function (): void {
    $user = User::factory()->create([
        'name' => 'Test User',
        'email' => 'test@example.com',
    ]);

    $response = $this->actingAs($user)
        ->fromRoute('user-profile.edit')
        ->patch(route('user-profile.update'), [
            'name' => 'Updated Name',
            'email' => 'test@example.com',
        ]);

    $response->assertRedirectToRoute('user-profile.edit')
        ->assertSessionDoesntHaveErrors();
});

it('refuses a profile address in mixed case', function (): void {
    $user = User::factory()->create(['email' => 'ada@example.com']);

    $response = $this->actingAs($user)
        ->fromRoute('user-profile.edit')
        ->patch(route('user-profile.update'), [
            'name' => 'Ada Lovelace',
            'email' => 'Ada.Lovelace@Example.COM',
        ]);

    // `ValidEmail` refuses a capital too, so the message is what shows that
    // the `lowercase` rule is the one answering.
    $response->assertRedirectToRoute('user-profile.edit')
        ->assertSessionHasErrors(['email' => __('validation.lowercase', ['attribute' => 'email'])]);

    expect($user->refresh()->email)->toBe('ada@example.com');
})->group('email-case');
