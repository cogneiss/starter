<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Every permission the application knows about. Adding a permission anywhere
 * else fails the authorization convention test, so add it here first.
 */
final class PermissionCatalog
{
    /**
     * @return list<PermissionDefinition>
     */
    public static function all(): array
    {
        return [
            new PermissionDefinition(
                'organization.view',
                'Organization',
                'View the organization',
                'See the organization name, slug and settings.',
            ),
            new PermissionDefinition(
                'organization.update',
                'Organization',
                'Update the organization',
                'Change the organization name and its security settings.',
            ),
            new PermissionDefinition(
                'organization.delete',
                'Organization',
                'Delete the organization',
                'Permanently delete the organization and everything in it.',
            ),
            new PermissionDefinition(
                'members.view',
                'Members',
                'View members',
                'See who belongs to the organization and what role they hold.',
            ),
            new PermissionDefinition(
                'members.invite',
                'Members',
                'Invite members',
                'Send, resend and revoke invitations to join the organization.',
            ),
            new PermissionDefinition(
                'members.update',
                'Members',
                'Update members',
                "Change a member's role, suspend them and reactivate them.",
            ),
            new PermissionDefinition(
                'members.remove',
                'Members',
                'Remove members',
                'Remove a member from the organization.',
            ),
            new PermissionDefinition(
                'roles.view',
                'Roles',
                'View roles',
                'See the roles available in the organization and their permissions.',
            ),
            new PermissionDefinition(
                'ai.view',
                'AI',
                'View AI usage',
                'See what the organization spent on AI and which requests were refused.',
            ),
            new PermissionDefinition(
                'ai.grant',
                'AI',
                'Grant AI credit',
                'Add AI credit to the organization.',
            ),
            new PermissionDefinition(
                'imports.view',
                'Imports',
                'View imports',
                'See the files that have been imported and which lines failed.',
            ),
            new PermissionDefinition(
                'imports.run',
                'Imports',
                'Run imports',
                'Upload a file and create records from it in bulk.',
            ),
            new PermissionDefinition(
                'api.tokens.view',
                'API',
                'View API tokens',
                "See the organization's API tokens and when they were last used.",
            ),
            new PermissionDefinition(
                'api.tokens.manage',
                'API',
                'Manage API tokens',
                'Create and revoke API tokens for the organization.',
            ),
            new PermissionDefinition(
                'api.usage.view',
                'API',
                'View API usage',
                'See how much the organization has used the API and by which tokens.',
            ),
            new PermissionDefinition(
                'roles.manage',
                'Roles',
                'Manage roles',
                "Create, edit and delete the organization's own roles.",
            ),
        ];
    }

    /**
     * @return list<string>
     */
    public static function names(): array
    {
        return array_map(
            static fn (PermissionDefinition $definition): string => $definition->name,
            self::all(),
        );
    }

    /**
     * The names in a group, for role templates that grant a whole verb.
     *
     * @return list<string>
     */
    public static function endingWith(string $verb): array
    {
        return array_values(array_filter(
            self::names(),
            static fn (string $name): bool => str_ends_with($name, '.'.$verb),
        ));
    }
}
