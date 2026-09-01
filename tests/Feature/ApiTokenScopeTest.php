<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;

/**
 * The request's organization comes from the token and nothing else. Every
 * channel a client could use to name a different organization — header, body,
 * query string, subdomain, the owner's own current organization, a session
 * cookie riding along — is tried here and must change nothing.
 */
beforeEach(function (): void {
    $this->organization = Organization::factory()->create();
    $this->foreign = Organization::factory()->create();

    $this->member = User::factory()->forOrganization($this->organization)->create();
    $this->stranger = User::factory()->forOrganization($this->foreign)->create();

    [$this->token, $this->bearer] = apiBearer($this->organization, user: $this->member);
});

it('org header cannot switch organization', function (): void {
    foreach ([
        ['X-Organization-Id' => $this->foreign->id],
        ['X-Organization' => $this->foreign->slug],
    ] as $headers) {
        $response = $this->withHeader('Authorization', $this->bearer)
            ->withHeaders($headers)
            ->getJson('/api/v1/users');

        $response->assertNotFound();

        expect($response->content())->not->toContain($this->stranger->id);
    }
});

it('foreign id is a 404 not 403', function (): void {
    $response = $this->withHeader('Authorization', $this->bearer)
        ->getJson('/api/v1/users/'.$this->stranger->id);

    $response->assertNotFound();

    expect($response->status())->not->toBe(403)
        ->and($response->content())->not->toContain($this->stranger->id);

    $this->withHeader('Authorization', $this->bearer)
        ->getJson('/api/v1/users/'.$this->member->id)
        ->assertOk();
});

it('body field cannot switch organization', function (): void {
    $response = $this->withHeader('Authorization', $this->bearer)
        ->json('GET', '/api/v1/users', ['organization_id' => $this->foreign->id]);

    $response->assertNotFound();

    expect($response->content())->not->toContain($this->stranger->id);
});

it('subdomain cannot switch organization', function (): void {
    $host = (string) parse_url(config()->string('app.url'), PHP_URL_HOST);

    $response = $this->withHeader('Authorization', $this->bearer)
        ->getJson('http://'.$this->foreign->slug.'.'.$host.'/api/v1/users');

    $response->assertNotFound();

    expect($response->content())->not->toContain($this->stranger->id);
});

it('query string cannot switch organization', function (): void {
    foreach ([
        '?organization_id='.$this->foreign->id,
        '?org='.$this->foreign->slug,
    ] as $query) {
        $response = $this->withHeader('Authorization', $this->bearer)
            ->getJson('/api/v1/users'.$query);

        $response->assertNotFound();

        expect($response->content())->not->toContain($this->stranger->id);
    }
});

it('owner current organization does not affect the token scope', function (): void {
    OrganizationMembership::factory()->create([
        'organization_id' => $this->foreign->id,
        'user_id' => $this->member->id,
    ]);

    $this->member->forceFill(['current_organization_id' => $this->foreign->id])->save();

    $response = $this->withHeader('Authorization', $this->bearer)
        ->getJson('/api/v1/users')
        ->assertOk();

    expect($response->content())
        ->toContain($this->member->id)
        ->not->toContain($this->stranger->id);
});

it('session cookie alongside the bearer token is ignored', function (): void {
    // A web session never authenticates the API: the guard only accepts a real
    // bearer token, so a session resolving to another organization gets 401
    // rather than a response scoped to it.
    $response = $this->actingAs($this->stranger)
        ->withHeader('Authorization', $this->bearer)
        ->getJson('/api/v1/users');

    $response->assertUnauthorized();

    expect($response->content())->not->toContain($this->stranger->id);
});

it('unknown resource key is a 404 json', function (): void {
    foreach (['/api/v1/not-a-resource', '/api/v1/not-a-resource/some-id'] as $url) {
        $response = $this->withHeader('Authorization', $this->bearer)->get($url);

        $response->assertNotFound()
            ->assertHeader('Content-Type', 'application/json');

        expect($response->content())
            ->not->toContain('Exception')
            ->not->toContain(base_path());
    }
});
