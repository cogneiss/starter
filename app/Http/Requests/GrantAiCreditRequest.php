<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class GrantAiCreditRequest extends FormRequest
{
    /**
     * The most one grant may post: a thousand dollars in micros.
     */
    public const int MAX_MICROS = 1_000_000_000;

    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'organization_id' => ['required', 'uuid', Rule::exists('organizations', 'id')],
            'micros' => ['required', 'integer', 'min:1', 'max:'.self::MAX_MICROS],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }
}
