<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\User;

/**
 * Every API failure is JSON — never a redirect, never an HTML page — one case
 * per status the surface can produce.
 */
beforeEach(function (): void {
    $this->organization = Organization::factory()->create();
    $this->member = User::factory()->forOrganization($this->organization)->create();

    [$this->token, $this->bearer] = apiBearer($this->organization, user: $this->member);
});

it('401 is json and never a redirect', function (): void {
    $response = $this->get('/api/v1/users');

    $response->assertUnauthorized()
        ->assertHeader('Content-Type', 'application/json');

    expect($response->headers->get('Location'))->toBeNull();
});

it('garbage bearer token is a 401 json', function (): void {
    foreach (['Bearer garbage', 'Bearer aaa|bbb', 'Bearer '.str_repeat('x', 300)] as $header) {
        $response = $this->withHeader('Authorization', $header)->get('/api/v1/users');

        $response->assertUnauthorized()
            ->assertHeader('Content-Type', 'application/json');
    }
});

it('403 is json', function (): void {
    [, $bearer] = apiBearer($this->organization, abilities: []);

    $this->withHeader('Authorization', $bearer)
        ->get('/api/v1/users')
        ->assertForbidden()
        ->assertHeader('Content-Type', 'application/json');
});

it('404 is json', function (): void {
    $this->withHeader('Authorization', $this->bearer)
        ->get('/api/v1/users/'.fake()->uuid())
        ->assertNotFound()
        ->assertHeader('Content-Type', 'application/json');
});

it('422 carries an error bag', function (): void {
    $this->withHeader('Authorization', $this->bearer)
        ->get('/api/v1/users?per=nope')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['per']);
});

it('429 is json and carries retry-after', function (): void {
    config()->set('api.rate_tiers.tiers.standard', ['per_token' => 1, 'per_organization' => 1]);

    $this->withHeader('Authorization', $this->bearer)->get('/api/v1/users')->assertOk();

    $this->withHeader('Authorization', $this->bearer)
        ->get('/api/v1/users')
        ->assertStatus(429)
        ->assertHeader('Retry-After')
        ->assertHeader('Content-Type', 'application/json');
});

it('unsupported method on api routes is 405 json and never writes', function (): void {
    foreach (['post', 'put', 'patch', 'delete'] as $method) {
        foreach (['/api/v1/users', '/api/v1/users/'.$this->member->id] as $url) {
            $this->withHeader('Authorization', $this->bearer)
                ->{$method.'Json'}($url, ['name' => 'Changed'])
                ->assertStatus(405);
        }
    }

    expect($this->member->refresh()->name)->not->toBe('Changed');
});
