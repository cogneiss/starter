<?php

declare(strict_types=1);

namespace App\Actions;

use App\Ai\Agents\FieldDrafter;
use App\Ai\DraftableFields;
use App\Enums\KnownFeatures;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Drafts the text of one named form field.
 *
 * The field must be on the DraftableFields allowlist, the organization must
 * have the feature on, and the member must hold the ability that guards the
 * form the field lives on. Anything else is a 404, so the endpoint does not
 * reveal which fields exist. The draft goes out through FieldDrafter, so quota,
 * credit metering and the audit row are the agent pipeline's, not this class's.
 */
final readonly class DraftFieldText
{
    public function handle(User $user, Organization $organization, string $field, string $text, ?string $instruction): string
    {
        $definition = DraftableFields::find($field);

        abort_if(
            $definition === null
            || ! KnownFeatures::AiAssistEnabled->enabledFor($organization)
            || ! Gate::forUser($user)->allows($definition['ability']),
            404,
        );

        return new FieldDrafter($user, $organization)->draft($definition['purpose'], $text, $instruction);
    }
}
