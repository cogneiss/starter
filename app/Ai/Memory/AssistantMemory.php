<?php

declare(strict_types=1);

namespace App\Ai\Memory;

use App\Models\AiMemory;
use App\Models\Organization;
use App\Models\User;
use App\Support\UntrustedContent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * What the assistant knows about one person in one organization.
 *
 * Both halves of that sentence are the control. A memory read filtered by
 * organization alone hands a colleague someone else's notes; filtered by user
 * alone it follows a person across organizations, which is the same leak the
 * rest of the layer spends its time preventing. The predicate lives in one
 * place so there is one thing to remove and one test to go red when it is.
 *
 * Remembered text is a customer's words, so it reaches the prompt fenced: the
 * model is told it is data, never instructions.
 */
final readonly class AssistantMemory
{
    public function __construct(
        private User $user,
        private Organization $organization,
    ) {}

    /**
     * The memory block for the system prompt, or nothing at all when this
     * person has none — an empty fence is a prompt telling the model to expect
     * facts that are not there.
     */
    public function instructions(): string
    {
        $facts = $this->facts();

        if ($facts === []) {
            return '';
        }

        return UntrustedContent::fence(implode(PHP_EOL, $facts), 'assistant memory');
    }

    /**
     * Record a fact, replacing whatever was under that key before, and drop the
     * least recently touched rows once the cap is passed. Without the cap the
     * prompt grows with every conversation until the model reads more memory
     * than question.
     */
    public function remember(string $key, string $value, string $source): AiMemory
    {
        return DB::transaction(function () use ($key, $value, $source): AiMemory {
            $memory = $this->query()->updateOrCreate(
                ['key' => $key],
                [
                    'organization_id' => $this->organization->id,
                    'user_id' => $this->user->id,
                    'value' => $value,
                    'source' => $source,
                ],
            );

            $this->evict();

            return $memory;
        });
    }

    /**
     * Every fact this person has here, newest first, as the model reads them.
     *
     * @return list<string>
     */
    private function facts(): array
    {
        return array_values($this->query()
            ->where(fn (Builder $unexpired): Builder => $unexpired
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>', now()))
            ->orderByDesc('updated_at')
            ->limit(config()->integer('ai.memory.max_facts'))
            ->get()
            ->map(fn (AiMemory $memory): string => $memory->key.': '.$memory->value)
            ->all());
    }

    private function evict(): void
    {
        $keep = $this->query()
            ->orderByDesc('updated_at')
            ->limit(config()->integer('ai.memory.max_facts'))
            ->pluck('id');

        $this->query()->whereNotIn('id', $keep)->delete();
    }

    /**
     * @return Builder<AiMemory>
     */
    private function query(): Builder
    {
        return AiMemory::query()
            ->where('organization_id', $this->organization->id)
            ->where('user_id', $this->user->id);
    }
}
