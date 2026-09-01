<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Rules\PublicHttpsUrl;
use App\Webhooks\WebhookEvents;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The organization is deliberately absent from these rules: an endpoint
 * belongs to the organization the session is acting in, and nothing the
 * request says can change that.
 */
final class StoreWebhookEndpointRequest extends FormRequest
{
    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'url' => ['required', 'string', 'max:255', resolve(PublicHttpsUrl::class)],
            'description' => ['nullable', 'string', 'max:255'],
            'events' => ['required', 'array'],
            'events.*' => ['string', Rule::in(resolve(WebhookEvents::class)->keys())],
        ];
    }
}
