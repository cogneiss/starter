<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use App\Rules\ValidEmail;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateUserRequest extends FormRequest
{
    /**
     * An address that is already stored is not held to `ValidEmail`. A social
     * sign-in can store one the rule refuses, and that member must still be
     * able to save a new name.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->user();
        assert($user instanceof User);

        return [
            'name' => ['required', 'string', 'max:255'],

            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                Rule::when($this->input('email') !== $user->email, [new ValidEmail]),
                'max:255',
                Rule::unique(User::class)->ignore($user->id),
            ],
        ];
    }
}
