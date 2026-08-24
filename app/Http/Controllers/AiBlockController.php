<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\StreamBlocks;
use App\Ai\Agents\BlockComposer;
use App\Models\Organization;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams an agent's answer as newline-delimited blocks.
 *
 * The response body is a block per line rather than the model's own text, so
 * the browser never has to parse a partial answer and never sees markup the
 * server has not already sanitized.
 */
final readonly class AiBlockController
{
    public function store(
        Request $request,
        #[CurrentUser] User $user,
        OrganizationContext $context,
        StreamBlocks $blocks,
    ): StreamedResponse {
        $request->validate([
            'prompt' => ['required', 'string', 'max:2000'],
        ]);

        $prompt = $request->string('prompt')->toString();

        // The `organization` middleware has already turned away a request with
        // nothing bound, so the context is never empty here.
        $organization = $context->get();

        assert($organization instanceof Organization);

        $agent = new BlockComposer($user, $organization);

        return response()->stream(function () use ($blocks, $agent, $prompt): void {
            foreach ($blocks->handle($agent, $prompt) as $block) {
                echo json_encode($block->toArray(), JSON_THROW_ON_ERROR)."\n";

                flush();
            }
        }, headers: [
            'Content-Type' => 'application/x-ndjson',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
