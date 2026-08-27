<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Resources\ResourceRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Naming a view, and changing what it is called or whether it opens by default.
 *
 * The stored query is not validated here beyond being an array. It cannot be:
 * what a sort column or a filter key means belongs to the resource, and the
 * resource is free to change its mind about both between the save and the next
 * read. `ResourceQuery` is the one place that decides, and it decides again on
 * every read rather than once at the door.
 */
final class SavedSearchRequest extends FormRequest
{
    /**
     * @return array<string, array<mixed>|string>
     */
    public function rules(): array
    {
        if ($this->isMethod('post')) {
            return [
                'resource' => ['required', 'string', Rule::in(resolve(ResourceRegistry::class)->keys())],
                'name' => ['required', 'string', 'max:60'],
                'query' => ['array'],
                'is_default' => ['boolean'],
            ];
        }

        return [
            'name' => ['sometimes', 'required', 'string', 'max:60'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * The query as it arrived, after validation has established it is an array.
     *
     * @return array<array-key, mixed>
     */
    public function savedQuery(): array
    {
        /** @var array<array-key, mixed> $query */
        $query = $this->validated('query') ?? [];

        return $query;
    }
}
