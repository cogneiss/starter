<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Actions\CreateConfirmToken;
use App\Ai\Agents\Concerns\HasDefaultMiddleware;
use App\Ai\Blocks\ConfirmBlock;
use App\Ai\Concerns\OrganizationScopedAgent;
use App\Ai\Contracts\OrganizationScoped;
use App\Data\InviteMemberData;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasMiddleware;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

/**
 * The first vertical: someone describes who should be invited, and this drafts
 * the invitation.
 *
 * It drafts and nothing else. The model answers with two fields, and those two
 * fields become a proposal the person still has to approve — the invitation
 * itself is written later by App\Actions\CreateOrganizationInvitation, from a
 * request the person made. That is the confirm gate, and it is the whole reason
 * a model may take part in a write at all.
 */
final class InvitationDrafter implements Agent, HasMiddleware, HasStructuredOutput, OrganizationScoped
{
    use HasDefaultMiddleware;
    use OrganizationScopedAgent;
    use Promptable;

    /**
     * The `ai.actions` key this vertical proposes, never a class name a model
     * could talk us into resolving.
     */
    public const string ACTION = 'invite-member';

    public function instructions(): string
    {
        return mb_trim((string) file_get_contents(resource_path('prompts/invitation-drafter.md')));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'email' => $schema->string()->required()->description('The email address to invite.'),
            'role' => $schema->string()->required()->description('The role to invite them as.'),
        ];
    }

    /**
     * Draft an invitation from a request, as a proposal to confirm.
     */
    public function draft(string $request): ConfirmBlock
    {
        $answer = json_decode($this->prompt($request)->text, true, 512, JSON_THROW_ON_ERROR);

        // The model's answer is data, and untrusted data at that: it is validated
        // into the action's own Data object before anything is proposed from it.
        $draft = InviteMemberData::validateAndCreate(is_array($answer) ? $answer : []);

        return $this->propose(['email' => $draft->email, 'role' => $draft->role]);
    }

    /**
     * @param  array<string, string>  $fields
     */
    private function propose(array $fields): ConfirmBlock
    {
        $token = resolve(CreateConfirmToken::class)->handle($this->user, self::ACTION, $fields);

        return new ConfirmBlock($token->id);
    }
}
