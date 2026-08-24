<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Organization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateOrganizationSwitchRequest extends FormRequest
{
    /**
     * @return array<string, array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // `bail` and `uuid` before the lookup: on PostgreSQL, comparing a
            // non-UUID string against a uuid column is a database error rather
            // than an empty result.
            'organization' => ['bail', 'required', 'string', 'uuid', Rule::exists(Organization::class, 'id')->whereNull('deleted_at')],
        ];
    }
}
