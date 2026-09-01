<?php

declare(strict_types=1);

use App\Models\AiConfirmToken;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Support\ApiAbilities;

/**
 * The read API serves every registered resource through one controller, and
 * every one of them is scoped at the query. Each resource gets a record seeded
 * in another organization, and that record must be invisible in the list and a
 * 404 by id.
 */
beforeEach(function (): void {
    $this->organization = Organization::factory()->create();
    $this->foreign = Organization::factory()->create();

    $this->member = User::factory()->forOrganization($this->organization)->create();
    $this->stranger = User::factory()->forOrganization($this->foreign)->create();

    [$this->token, $this->bearer] = apiBearer(
        $this->organization,
        abilities: ApiAbilities::all(),
        user: $this->member,
    );
});

it('every registered resource is reachable and scoped', function (): void {
    $foreignIds = [
        'users' => $this->stranger->id,
        'organizations' => $this->foreign->id,
        'organization-members' => OrganizationMembership::query()
            ->where('user_id', $this->stranger->id)->sole()->id,
        'organization-invitations' => OrganizationInvitation::factory()->create([
            'organization_id' => $this->foreign->id,
            'invited_by_user_id' => $this->stranger->id,
        ])->id,
        'ai-confirm-tokens' => AiConfirmToken::factory()->create([
            'organization_id' => $this->foreign->id,
            'user_id' => $this->stranger->id,
        ])->id,
    ];

    foreach ($foreignIds as $key => $foreignId) {
        $list = $this->withHeader('Authorization', $this->bearer)
            ->getJson('/api/v1/'.$key);

        $list->assertOk()->assertJsonStructure(['rows', 'total', 'pages']);

        expect($list->content())->not->toContain($foreignId);

        $this->withHeader('Authorization', $this->bearer)
            ->getJson('/api/v1/'.$key.'/'.$foreignId)
            ->assertNotFound();
    }
});

it('show scopes at the query level', function (): void {
    $this->withHeader('Authorization', $this->bearer)
        ->getJson('/api/v1/users/'.$this->member->id)
        ->assertOk()
        ->assertJsonPath('id', $this->member->id);

    $foreign = $this->withHeader('Authorization', $this->bearer)
        ->getJson('/api/v1/users/'.$this->stranger->id);

    $foreign->assertNotFound();

    expect($foreign->content())->not->toContain($this->stranger->id);
});

it('unknown sort and filter are ignored', function (): void {
    $response = $this->withHeader('Authorization', $this->bearer)
        ->getJson('/api/v1/users?sort=password&f[nope]=1');

    $response->assertOk()
        ->assertJsonPath('query.sort', 'created_at')
        ->assertJsonPath('total', 1);
});
