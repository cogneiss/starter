<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Rules\PublicHttpsUrl;
use App\Webhooks\WebhookEvents;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateWebhookEndpointRequest extends FormRequest
{
    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'url' => ['sometimes', 'required', 'string', 'max:255', resolve(PublicHttpsUrl::class)],
            'description' => ['nullable', 'string', 'max:255'],
            'events' => ['sometimes', 'required', 'array'],
            'events.*' => ['string', Rule::in(resolve(WebhookEvents::class)->keys())],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
