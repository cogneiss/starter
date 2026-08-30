<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateUserLocaleRequest extends FormRequest
{
    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'locale' => ['required', 'string', Rule::in(config()->array('app.supported_locales'))],
        ];
    }

    /**
     * The chosen language, already known to be one this application serves.
     */
    public function locale(): string
    {
        return $this->string('locale')->value();
    }
}
