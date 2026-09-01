<?php

declare(strict_types=1);

use App\Models\Activity;
use App\Models\AiAuditLog;
use App\Models\ImpersonationLog;
use App\Models\LoginHistory;
use App\Models\Organization;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Support\Facades\Schema;

/**
 * ImpersonationLog, LoginHistory and AiAuditLog predate the audit table and
 * keep their own tables untouched. Each row they write bridges exactly one
 * activity entry, so the audit log is the one place to read everything.
 */
beforeEach(function (): void {
    $this->organization = Organization::factory()->create();
});

it('keeps the bridged tables intact — no destructive migration', function (): void {
    expect(Schema::hasColumns('impersonation_logs', ['impersonator_user_id', 'impersonated_user_id', 'started_at', 'ip_address', 'user_agent']))->toBeTrue()
        ->and(Schema::hasColumns('login_histories', ['user_id', 'email', 'ip_address', 'user_agent', 'successful']))->toBeTrue()
        ->and(Schema::hasColumns('ai_audit_logs', ['organization_id', 'user_id', 'agent']))->toBeTrue();
});

it('bridges an impersonation to exactly one activity entry, keeping its own row', function (): void {
    $log = ImpersonationLog::factory()->create(['organization_id' => $this->organization->id]);

    expect(ImpersonationLog::query()->whereKey($log->id)->exists())->toBeTrue();

    $entries = Activity::withoutOrganizationScope()
        ->where('subject_type', $log->getMorphClass())
        ->where('subject_id', $log->id)
        ->get();

    expect($entries)->toHaveCount(1)
        ->and($entries->sole()->organization_id)->toBe($this->organization->id)
        ->and($entries->sole()->event)->toBe('created');
});

it('bridges a login to exactly one activity entry in the user’s organization', function (): void {
    $user = User::factory()->forOrganization($this->organization)->create();

    $login = LoginHistory::factory()->create(['user_id' => $user->id]);

    expect(LoginHistory::query()->whereKey($login->id)->exists())->toBeTrue();

    $entries = Activity::withoutOrganizationScope()
        ->where('subject_type', $login->getMorphClass())
        ->where('subject_id', $login->id)
        ->get();

    expect($entries)->toHaveCount(1)
        ->and($entries->sole()->organization_id)->toBe($this->organization->id);
});

it('writes no activity for a login that resolves to no organization — deliberately', function (): void {
    // A failed attempt against an unknown address has a null user and no
    // membership. activity_log.organization_id is NOT NULL by design — every
    // entry belongs to a tenant — so an unattributable login keeps its own
    // login_histories row and bridges nothing. That row is still readable
    // where it always was; it just has no tenant audit trail to join.
    $login = LoginHistory::factory()->create(['user_id' => null, 'successful' => false]);

    expect(LoginHistory::query()->whereKey($login->id)->exists())->toBeTrue()
        ->and(Activity::withoutOrganizationScope()
            ->where('subject_type', $login->getMorphClass())
            ->where('subject_id', $login->id)
            ->count())->toBe(0);
});

it('audits an AI audit row through the wildcard, exactly once, keeping its own row', function (): void {
    $log = resolve(OrganizationContext::class)->runAs(
        $this->organization,
        fn (): AiAuditLog => AiAuditLog::factory()->create(['organization_id' => $this->organization->id]),
    );

    expect(AiAuditLog::withoutOrganizationScope()->whereKey($log->id)->exists())->toBeTrue();

    $entries = Activity::withoutOrganizationScope()
        ->where('subject_type', $log->getMorphClass())
        ->where('subject_id', $log->id)
        ->get();

    expect($entries)->toHaveCount(1)
        ->and($entries->sole()->organization_id)->toBe($this->organization->id)
        ->and($entries->sole()->event)->toBe('created');
});
