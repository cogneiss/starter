<?php

declare(strict_types=1);

namespace Tests\Fixtures\Ai;

use App\Models\Organization;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * A tool that reads an organization without asking anyone whether it may.
 *
 * It exists so the arch guard has something it is supposed to reject: a guard
 * that has only ever seen compliant tools proves nothing.
 */
final readonly class UnauthorizedFixtureTool implements Tool
{
    public function __construct(private Organization $organization) {}

    public function description(): string
    {
        return 'Reads the name of an organization without checking a policy.';
    }

    public function handle(Request $request): string
    {
        return $this->organization->name;
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
