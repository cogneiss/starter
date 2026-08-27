<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\BulkMembershipAction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

final class BulkOrganizationMembershipRequest extends FormRequest
{
    /**
     * @return array<string, array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', new Enum(BulkMembershipAction::class)],
            'ids' => ['array'],
            'ids.*' => ['string'],
            // Absent means the page in front of the person. Present and true is
            // the person saying they mean every record the filters match.
            'all' => ['boolean'],
        ];
    }

    /**
     * The ticked rows, after validation has established they are strings.
     *
     * @return list<string>
     */
    public function ids(): array
    {
        /** @var list<string> $ids */
        $ids = $this->validated('ids') ?? [];

        return $ids;
    }
}
