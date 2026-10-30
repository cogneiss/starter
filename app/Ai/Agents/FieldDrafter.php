<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Ai\Agents\Concerns\HasDefaultMiddleware;
use App\Ai\Concerns\OrganizationScopedAgent;
use App\Ai\Contracts\OrganizationScoped;
use App\Support\AiTier;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasMiddleware;
use Laravel\Ai\Promptable;

/**
 * Drafts or improves the text of one form field.
 *
 * It answers with plain text and has no tools, so there is nothing for an
 * injected instruction to reach. The text is only ever put in the field for the
 * person to read, edit and save through the form's own validation.
 */
final class FieldDrafter implements Agent, HasMiddleware, OrganizationScoped
{
    use HasDefaultMiddleware;
    use OrganizationScopedAgent;
    use Promptable;

    public function instructions(): string
    {
        return mb_trim((string) file_get_contents(resource_path('prompts/field-drafter.md')));
    }

    public function draft(string $purpose, string $text, ?string $instruction): string
    {
        $tier = AiTier::for('cheap');

        return mb_trim($this->prompt(
            json_encode(['field' => $purpose, 'current_text' => $text, 'instruction' => $instruction], JSON_THROW_ON_ERROR),
            provider: $tier['provider'],
            model: $tier['model'],
        )->text);
    }
}
