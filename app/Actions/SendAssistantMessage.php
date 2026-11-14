<?php

declare(strict_types=1);

namespace App\Actions;

use App\Ai\Agents\Assistant;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\ConversationStore;
use Laravel\Ai\Models\Conversation;

/**
 * One turn with the assistant, saved under the member and the organization.
 *
 * The conversation row is created here rather than by the SDK's remember
 * middleware, because the SDK does not know about organizations. Creating it
 * first also gives it a title from the message itself, so naming it costs no
 * provider call.
 */
final readonly class SendAssistantMessage
{
    public function __construct(private AssistantBlocks $blocks) {}

    /**
     * @return array{conversation_id: string, text: string, blocks: list<array<mixed>>}
     */
    public function handle(User $user, Organization $organization, string $message, ?Conversation $conversation = null): array
    {
        $id = $conversation instanceof Conversation
            ? $conversation->id
            : $this->start($user, $organization, $message);

        $response = new Assistant($user, $organization)
            ->continue($id, as: $user)
            ->reply($message);

        return [
            'conversation_id' => $id,
            'text' => $response->text,
            'blocks' => $this->blocks->handle($response->toolResults->map(fn (object $result): mixed => $result->result)),
        ];
    }

    private function start(User $user, Organization $organization, string $message): string
    {
        $id = resolve(ConversationStore::class)->storeConversation(
            Conversation::participantType($user),
            Conversation::participantKey($user),
            Str::limit($message, 50, preserveWords: true),
        );

        Conversation::query()->whereKey($id)->update([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
        ]);

        return $id;
    }
}
