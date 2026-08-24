<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Ai\Agents\Concerns\HasDefaultMiddleware;
use App\Ai\Concerns\OrganizationScopedAgent;
use App\Ai\Contracts\OrganizationScoped;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasMiddleware;
use Laravel\Ai\Promptable;

/**
 * Answers in blocks rather than prose.
 *
 * One JSON object per line, so a block can be handed to the browser the moment
 * its line is complete instead of after the whole answer is. The instructions
 * are a request, not a guarantee — App\Ai\Blocks\BlockCollection is what
 * actually decides whether a line becomes a block.
 */
final class BlockComposer implements Agent, HasMiddleware, OrganizationScoped
{
    use HasDefaultMiddleware;
    use OrganizationScopedAgent;
    use Promptable;

    /**
     * The prompt is an asset, not a PHP literal: nothing is interpolated into
     * it on purpose — instructions() is the one string the model is told to
     * trust, and the convention guard in
     * tests/Unit/Conventions/InjectionConventionTest.php keeps it literal.
     */
    public function instructions(): string
    {
        return mb_trim((string) file_get_contents(resource_path('prompts/block-composer.md')));
    }
}
