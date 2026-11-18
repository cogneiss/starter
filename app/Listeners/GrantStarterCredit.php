<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Actions\GrantAiCredits;
use App\Events\OrganizationCreated;

final readonly class GrantStarterCredit
{
    public function __construct(private GrantAiCredits $grant) {}

    public function handle(OrganizationCreated $event): void
    {
        $micros = config()->integer('ai.credits.starter_micros');

        if ($micros > 0) {
            $this->grant->handle($event->organization, $micros, __('ui.credits.starter'));
        }
    }
}
