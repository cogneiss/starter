<?php

declare(strict_types=1);

namespace App\Providers;

use App\Support\AiAvailability;
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
        if (AiAvailability::faked()) {
            foreach (self::agents() as $agent) {
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
    private static function agents(): array
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
