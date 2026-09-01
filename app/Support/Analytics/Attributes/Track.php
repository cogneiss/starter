<?php

declare(strict_types=1);

namespace App\Support\Analytics\Attributes;

use Attribute;

/**
 * Opts a model into analytics events beyond the created/deleted default,
 * e.g. #[Track(['updated'])]. Updated events carry changed attribute names
 * only, never values.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Track
{
    /**
     * @param  list<string>  $events
     */
    public function __construct(public array $events = []) {}
}
