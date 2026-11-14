<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\AssistantBlocks;
use App\Actions\AssistantConversations;
use App\Actions\SendAssistantMessage;
use App\Enums\KnownFeatures;
use App\Http\Requests\SendAssistantMessageRequest;
use App\Models\Organization;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Ai\Models\Conversation;
use Laravel\Ai\Models\ConversationMessage;

/**
 * The assistant's pages and its one write endpoint.
 *
 * A member sees their own conversations in the organization they are working in
 * and nobody else's: the scope is part of the query, so another member's id and
 * another organization's id are both a 404. An organization with the AI feature
 * off gets a 404 for the whole area, not an empty page.
 */
final readonly class AssistantController
{
    public function __construct(
        private OrganizationContext $context,
        private AssistantConversations $conversations,
    ) {}

    public function index(#[CurrentUser] User $user): Response
    {
        $organization = $this->organization();

        return Inertia::render('assistant/index', [
            'conversations' => Inertia::defer(fn (): array => $this->conversations->handle($user, $organization)
                ->latest('updated_at')
                ->limit(50)
                ->get(['id', 'title', 'updated_at'])
                ->map(fn (Conversation $conversation): array => $conversation->only(['id', 'title', 'updated_at']))
                ->all()),
        ]);
    }

    public function create(): Response
    {
        $this->organization();

        return Inertia::render('assistant/show', ['conversation' => null, 'messages' => []]);
    }

    public function show(#[CurrentUser] User $user, AssistantBlocks $blocks, string $conversation): Response
    {
        $found = $this->conversations->handle($user, $this->organization())->findOrFail($conversation);

        return Inertia::render('assistant/show', [
            'conversation' => $found->only(['id', 'title']),
            'messages' => $found->messages()
                ->whereIn('role', ['user', 'assistant'])
                ->oldest()
                ->get()
                ->map(fn (ConversationMessage $message): array => [
                    'id' => $message->id,
                    'role' => $message->role,
                    'text' => (string) $message->content,
                    'blocks' => $message->role === 'assistant' ? $blocks->handle(array_column($message->tool_results, 'result')) : [],
                ])
                ->all(),
        ]);
    }

    public function store(SendAssistantMessageRequest $request, #[CurrentUser] User $user, SendAssistantMessage $send): JsonResponse
    {
        $organization = $this->organization();

        $conversation = $request->filled('conversation_id')
            ? $this->conversations->handle($user, $organization)->findOrFail($request->string('conversation_id')->toString())
            : null;

        return response()->json($send->handle($user, $organization, $request->string('message')->toString(), $conversation));
    }

    private function organization(): Organization
    {
        $organization = $this->context->get();

        abort_unless($organization instanceof Organization && KnownFeatures::AiAssistEnabled->enabledFor($organization), 404);

        return $organization;
    }
}
