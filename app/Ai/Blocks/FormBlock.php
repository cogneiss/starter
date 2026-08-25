<?php

declare(strict_types=1);

namespace App\Ai\Blocks;

use App\Ai\ConfirmableActions;
use App\Enums\AiBlockType;
use Illuminate\Support\Str;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionProperty;
use Spatie\LaravelData\Attributes\Computed;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A form for one of the `ai.actions` keys, prefilled with what the model
 * suggested. The fields are read off the action's own Data object rather than
 * off the model's payload, so a block cannot introduce a field the action does
 * not have, and naming a key that is not on the allowlist produces no block at
 * all. Submitting it proposes the action; it never runs one.
 */
#[TypeScript('AiFormBlock')]
final class FormBlock extends Data implements AiBlock
{
    public AiBlockType $type = AiBlockType::Form;

    #[Computed]
    public string $summary;

    /** @var list<AiFormField> */
    #[Computed]
    public array $fields;

    /**
     * @param  array<string, string>  $values
     */
    public function __construct(
        public string $action,
        array $values = [],
    ) {
        $action = ConfirmableActions::find($this->action)
            ?? throw new InvalidArgumentException("There is no confirmable action named [{$this->action}].");

        $this->fields = array_map(
            fn (ReflectionProperty $property): AiFormField => new AiFormField(
                name: $property->getName(),
                value: (string) ($values[$property->getName()] ?? ''),
            ),
            new ReflectionClass($action->dataClass())->getProperties(ReflectionProperty::IS_PUBLIC),
        );

        $this->summary = Str::headline($this->action);
    }
}
