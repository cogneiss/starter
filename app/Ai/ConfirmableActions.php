<?php

declare(strict_types=1);

namespace App\Ai;

use App\Ai\Actions\RememberAssistantFact;
use App\Ai\Contracts\ConfirmableAction;

/**
 * The allowlist of writes an agent may propose.
 *
 * `config('ai.actions')` is a plain array, so every reader of it would otherwise
 * have to decide for itself what an entry that is not a confirmable action
 * means. It means the key does not exist: an entry that is not a class
 * implementing the contract is dropped here rather than resolved anywhere else.
 */
final readonly class ConfirmableActions
{
    /**
     * Actions the layer itself needs, so they exist whatever `ai.actions` holds.
     */
    private const array BUILT_IN = [
        'remember-fact' => RememberAssistantFact::class,
    ];

    /**
     * @return array<string, class-string<ConfirmableAction>>
     */
    public static function all(): array
    {
        $actions = [];

        foreach ([...config()->array('ai.actions'), ...self::BUILT_IN] as $key => $class) {
            if (is_string($key) && is_string($class) && is_subclass_of($class, ConfirmableAction::class)) {
                $actions[$key] = $class;
            }
        }

        return $actions;
    }

    /**
     * The action a key names, or null when nothing on the allowlist answers to
     * it. A key the model invented lands here.
     */
    public static function find(string $key): ?ConfirmableAction
    {
        $class = self::all()[$key] ?? null;

        if ($class === null) {
            return null;
        }

        $action = app()->make($class);

        // A container binding can answer for the class with anything at all, so
        // the contract is checked on the instance and not only on the name.
        return $action instanceof ConfirmableAction ? $action : null;
    }
}
