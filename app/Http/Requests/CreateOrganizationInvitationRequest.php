<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Rules\ValidEmail;
use Illuminate\Foundation\Http\FormRequest;

final class CreateOrganizationInvitationRequest extends FormRequest
{
    /**
     * @return array<string, array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'lowercase', 'max:255', new ValidEmail],
            'role' => ['required', 'string', 'max:255'],
        ];
    }
}
