<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateConfirmToken;
use App\Ai\Blocks\ConfirmBlock;
use App\Ai\ConfirmableActions;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Where a form block goes when it is submitted. It proposes the action and
 * answers with the confirm block for it; nothing is performed until the person
 * confirms, which is a second request against a second permission check.
 */
final readonly class AiProposalController
{
    public function store(Request $request, #[CurrentUser] User $user, CreateConfirmToken $tokens): JsonResponse
    {
        $request->validate([
            'action' => ['required', 'string', Rule::in(array_keys(ConfirmableActions::all()))],
            'fields' => ['array'],
        ]);

        // Only the named fields of the action are ever proposed, so a payload
        // carrying integer keys is not a payload this can build an action from.
        $fields = array_filter(
            $request->array('fields'),
            is_string(...),
            ARRAY_FILTER_USE_KEY,
        );

        $token = $tokens->handle($user, $request->string('action')->toString(), $fields);

        return response()->json(new ConfirmBlock($token->id)->toArray());
    }
}
