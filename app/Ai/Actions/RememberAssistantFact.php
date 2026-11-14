<?php

declare(strict_types=1);

namespace App\Ai\Actions;

use App\Ai\Contracts\ConfirmableAction;
use App\Ai\Memory\AssistantMemory;
use App\Models\AiMemory;
use App\Models\Organization;
use App\Models\User;
use App\Support\OrganizationContext;
use RuntimeException;
use Spatie\LaravelData\Data;

/**
 * The confirmed half of RememberFact. A fact enters assistant memory only after
 * the person it is about approved it, because stored memory is replayed into
 * every later prompt and a poisoned entry would outlive the conversation that
 * planted it.
 */
final readonly class RememberAssistantFact implements ConfirmableAction
{
    public function __construct(private OrganizationContext $context) {}

    public function dataClass(): string
    {
        return RememberFactData::class;
    }

    public function ability(): string
    {
        return 'organization.view';
    }

    public function summary(Data $payload): string
    {
        assert($payload instanceof RememberFactData);

        return "Remember {$payload->key}: {$payload->value}";
    }

    public function confirm(User $user, Data $payload): AiMemory
    {
        assert($payload instanceof RememberFactData);

        $organization = $this->context->get();

        throw_unless($organization instanceof Organization, RuntimeException::class, 'A memory needs an organization bound to the context.');

        return new AssistantMemory($user, $organization)->remember($payload->key, $payload->value, 'confirmed');
    }
}
