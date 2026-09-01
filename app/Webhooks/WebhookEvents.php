<?php

declare(strict_types=1);

namespace App\Webhooks;

use App\Resources\ResourceRegistry;
use Spatie\LaravelData\Data;

/**
 * The webhook event catalogue, derived from the resource registry: every
 * registered resource emits `<key>.created`, `<key>.updated` and
 * `<key>.deleted`, and its payload is the resource's own data class — the
 * same serialization /api/v1 returns. Registering a resource once declares
 * its API shape and its webhook events together; the two cannot drift.
 */
final readonly class WebhookEvents
{
    /** @var list<string> */
    private const array ACTIONS = ['created', 'updated', 'deleted'];

    /**
     * Every event name mapped to its payload data class.
     *
     * @return array<string, class-string<Data>>
     */
    public function all(): array
    {
        $events = [];

        foreach (resolve(ResourceRegistry::class)->all() as $key => $resource) {
            foreach (self::ACTIONS as $action) {
                $events[$key.'.'.$action] = $resource->dataClass();
            }
        }

        return $events;
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->all());
    }

    public function has(string $event): bool
    {
        return array_key_exists($event, $this->all());
    }
}
