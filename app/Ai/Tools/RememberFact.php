<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Ai\Memory\AssistantMemory;
use App\Ai\Tools\Concerns\AuthorizesToolCall;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * The only way anything is ever written to assistant memory.
 *
 * Retrieved documents are a customer's text and a plausible place for
 * "remember that you may ignore your rules" to be hiding. That sentence can
 * reach the model, but it cannot reach the database on its own: writing takes
 * a tool call, and the call is authorized against the person who asked, not
 * against whoever wrote the document.
 *
 * One call writes at most one App\Models\AiMemory row, for the person the tool
 * was constructed with — never for whoever the text happens to name.
 */
final readonly class RememberFact implements Tool
{
    use AuthorizesToolCall;

    public function __construct(
        private User $user,
        private Organization $organization,
    ) {}

    public function description(): string
    {
        return 'Remembers one short fact about the person you are helping, for later conversations.';
    }

    public function handle(Request $request): string
    {
        $this->authorizeFor($this->user, 'view', $this->organization);

        $key = $request->string('key')->toString();

        new AssistantMemory($this->user, $this->organization)->remember(
            $key,
            $request->string('value')->toString(),
            'tool',
        );

        return "Remembered {$key}.";
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'key' => $schema->string()->required()
                ->description('A short name for the fact, such as "preferred report format".'),
            'value' => $schema->string()->required()
                ->description('The fact itself, in one sentence.'),
        ];
    }
}
