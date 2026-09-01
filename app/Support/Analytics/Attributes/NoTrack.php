<?php

declare(strict_types=1);

namespace App\Support\Analytics\Attributes;

use Attribute;

/**
 * Opts a model out of analytics entirely.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class NoTrack {}
