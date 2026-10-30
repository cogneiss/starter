<?php

declare(strict_types=1);

namespace App\Ai;

/**
 * The form fields a member may ask the assistant to draft, by key.
 *
 * A field is listed with the gate ability that guards its form and a sentence
 * telling the model what the text is for. A key that is not here does not
 * exist; to make another field draftable, add it here and put `AiDraftButton`
 * beside it.
 */
final readonly class DraftableFields
{
    private const array FIELDS = [
        'announcement.body' => [
            'ability' => 'platform',
            'purpose' => 'the message of an in-app announcement banner, at most 500 characters',
        ],
    ];

    /**
     * @return array{ability: string, purpose: string}|null
     */
    public static function find(string $key): ?array
    {
        return self::FIELDS[$key] ?? null;
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::FIELDS);
    }
}
