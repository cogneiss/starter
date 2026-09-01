<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\ApiAbilities;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The organization is deliberately absent from these rules: a token belongs to
 * the organization the session is acting in, and nothing the request says can
 * change that.
 */
final class StoreApiTokenRequest extends FormRequest
{
    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:60'],
            'abilities' => ['required', 'array'],
            'abilities.*' => ['string', Rule::in(ApiAbilities::all())],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ];
    }
}
