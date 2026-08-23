<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Organization;
use App\Support\OrganizationContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateOrganizationRequest extends FormRequest
{
    /**
     * @return array<string, array<mixed>|string>
     */
    public function rules(): array
    {
        $organization = resolve(OrganizationContext::class)->get();
        assert($organization instanceof Organization);

        return [
            'name' => ['required', 'string', 'max:255'],

            'slug' => [
                'required',
                'string',
                'lowercase',
                'max:255',
                'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/',
                Rule::unique(Organization::class)->ignore($organization->id),
            ],

            'require_two_factor' => ['required', 'boolean'],
        ];
    }
}
