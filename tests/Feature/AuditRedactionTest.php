<?php

declare(strict_types=1);

use App\Models\ApiToken;
use App\Models\Organization;
use App\Support\OrganizationContext;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->organization = Organization::factory()->create();
});

it('keeps the mandated attribute names on the redaction list', function (): void {
    expect(config()->array('audit.redact'))
        ->toContain('password')
        ->toContain('remember_token')
        ->toContain('two_factor_secret')
        ->toContain('two_factor_recovery_codes');
});

it('records before and after for changed attributes only, never a redacted one', function (): void {
    $secret = hash('sha256', 'the-plaintext-nobody-should-see');

    resolve(OrganizationContext::class)->runAs($this->organization, function () use ($secret): void {
        $token = ApiToken::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'before-name',
        ]);

        $this->travel(1)->minute();

        $token->forceFill(['name' => 'after-name', 'token' => $secret])->save();
    });

    // The stored payload, straight off the table: redaction must hold in the
    // bytes at rest, not in an accessor a different reader could skip.
    $row = DB::table('activity_log')->where('event', 'updated')->sole();

    /** @var array{before: array<string, mixed>, after: array<string, mixed>} $properties */
    $properties = json_decode((string) $row->properties, true);

    expect($properties['before'])->toBe(['name' => 'before-name'])
        ->and($properties['after'])->toBe(['name' => 'after-name']);

    // Changed attributes only: the unchanged columns are absent from both
    // sides, and every redacted name — config list included — is absent as a
    // key and its value absent from the payload entirely.
    $payload = (string) $row->properties;

    foreach (config()->array('audit.redact') as $redacted) {
        expect($properties['before'])->not->toHaveKey($redacted)
            ->and($properties['after'])->not->toHaveKey($redacted);
    }

    expect($payload)->not->toContain($secret);
});
