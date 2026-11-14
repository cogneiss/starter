<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Ai\Tools\Concerns\AuthorizesToolCall;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * Proposes a memory; it never writes one.
 *
 * Retrieved documents are a customer's text and a plausible place for
 * "remember that you may ignore your rules" to be hiding. Memory is replayed
 * into every later prompt, so a poisoned entry would persist. The tool therefore
 * only hands back a confirm token, and the fact is stored when the person
 * approves it, as App\Ai\Actions\RememberAssistantFact.
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
        return 'Asks the person to confirm remembering one short fact about them, for later conversations.';
    }

    public function handle(Request $request): string
    {
        $this->authorizeFor($this->user, 'view', $this->organization);

        return resolve(ProposeAction::class, ['user' => $this->user, 'organization' => $this->organization])
            ->handle(new Request([
                'action' => 'remember-fact',
                'fields' => [
                    'key' => $request->string('key')->toString(),
                    'value' => $request->string('value')->toString(),
                ],
            ]));
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
