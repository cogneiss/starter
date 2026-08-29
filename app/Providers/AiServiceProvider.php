<?php

declare(strict_types=1);

namespace App\Providers;

use App\Exceptions\AiQuotaExceededException;
use App\Exceptions\BlockedEgressException;
use App\Support\AiAvailability;
use App\Support\UserFriendlyExceptionRegistrar;
use Illuminate\Support\ServiceProvider;
use Laravel\Ai\Ai;
use Laravel\Ai\AnonymousAgent;
use Laravel\Ai\StructuredAnonymousAgent;

/**
 * Keeps a key-less checkout usable.
 *
 * With no provider configured — or with AI_FAKE=true on a machine that does
 * have keys — every agent is answered by the SDK's fake gateway. Nothing
 * throws, nothing reaches the network, and the AI surfaces still render.
 */
final class AiServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // The two failures this layer knows better than the status code does.
        // They are registered here rather than in the exception handler so that
        // the handler stays ignorant of the AI layer.
        UserFriendlyExceptionRegistrar::register(
            AiQuotaExceededException::class,
            429,
            'This organization has used its AI allowance for now. It resets shortly.',
        );

        UserFriendlyExceptionRegistrar::register(
            BlockedEgressException::class,
            502,
            'The assistant tried to reach an address it is not allowed to. Nothing was sent.',
        );

        if (AiAvailability::faked()) {
            foreach ($this->agents() as $agent) {
                // No canned responses: the gateway then echoes the prompt back
                // as a fake answer, for as many prompts as arrive. A fixed list
                // would run out and start throwing halfway through a demo.
                Ai::fakeAgent($agent);
            }
        }
    }

    /**
     * Every agent class this application can prompt: the SDK's two anonymous
     * agents, plus each first-party agent. Agents live directly in
     * `app/Ai/Agents`; the subdirectories below it hold traits and support
     * classes, which are not promptable.
     *
     * @return list<class-string>
     */
    private function agents(): array
    {
        $directory = app_path('Ai/Agents');

        $files = is_dir($directory) ? (glob($directory.'/*.php') ?: []) : [];

        /** @var list<class-string> $discovered */
        $discovered = array_map(
            static fn (string $file): string => 'App\\Ai\\Agents\\'.basename($file, '.php'),
            $files,
        );

        return [AnonymousAgent::class, StructuredAnonymousAgent::class, ...$discovered];
    }
}
