<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\DraftFieldText;
use App\Ai\DraftableFields;
use App\Models\Organization;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Where a "Draft with AI" button posts. It answers with text for the person to
 * review; nothing is saved here.
 */
final readonly class AiDraftController
{
    public function store(Request $request, #[CurrentUser] User $user, OrganizationContext $context, DraftFieldText $draft): JsonResponse
    {
        $request->validate([
            'field' => ['required', 'string', Rule::in(DraftableFields::keys())],
            'text' => ['nullable', 'string', 'max:2000'],
            'instruction' => ['nullable', 'string', 'max:500'],
        ]);

        // The `organization` middleware has already turned away a request with
        // nothing bound, so the context is never empty here.
        $organization = $context->get();

        assert($organization instanceof Organization);

        return response()->json(['text' => $draft->handle(
            $user,
            $organization,
            $request->string('field')->toString(),
            $request->string('text')->toString(),
            $request->filled('instruction') ? $request->string('instruction')->toString() : null,
        )]);
    }
}
