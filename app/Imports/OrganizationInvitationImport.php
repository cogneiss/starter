<?php

declare(strict_types=1);

namespace App\Imports;

use App\Actions\CreateOrganizationInvitation;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Support\OrganizationContext;

/**
 * Invites people in bulk through the same Action the invite form calls.
 *
 * The ability is decided per row rather than once for the file, because the
 * rows do not all ask for the same thing: inviting somebody as a Member and
 * inviting them as an Owner are two different grants, and a file may contain
 * both.
 */
final readonly class OrganizationInvitationImport implements ImportContract
{
    public function __construct(
        private CreateOrganizationInvitation $invitations,
        private OrganizationContext $context,
    ) {}

    public function key(): string
    {
        return 'organization-invitations';
    }

    /**
     * @return list<string>
     */
    public function columns(): array
    {
        return ['email', 'role'];
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'role' => ['required', 'string'],
        ];
    }

    /**
     * @param  array<string, string>  $row
     */
    public function authorizeRow(User $user, array $row): bool
    {
        $role = $this->role($row);

        return $role instanceof Role && $user->can('grant', $role);
    }

    /**
     * @param  array<string, string>  $row
     */
    public function handle(User $user, array $row): void
    {
        $this->invitations->handle($this->organization(), $user, $row['email'], $row['role']);
    }

    /**
     * The role this row asks for, as this organization spells it.
     *
     * @param  array<string, string>  $row
     */
    private function role(array $row): ?Role
    {
        return Role::query()
            ->where('organization_id', $this->organization()->id)
            ->where('name', $row['role'] ?? '')
            ->first();
    }

    private function organization(): Organization
    {
        $organization = $this->context->get();

        assert($organization instanceof Organization);

        return $organization;
    }
}
