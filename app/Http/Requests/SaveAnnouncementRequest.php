<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\AnnouncementLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SaveAnnouncementRequest extends FormRequest
{
    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:500'],
            'level' => ['required', Rule::enum(AnnouncementLevel::class)],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'organization_id' => ['nullable', 'uuid', Rule::exists('organizations', 'id')],
        ];
    }
}
