<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Ai\Tools\Concerns\AuthorizesToolCall;
use App\Models\User;
use Composer\InstalledVersions;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * Tells a client which application it is talking to and what it runs on.
 *
 * Unlike the record tools there is no agent-side tool to delegate to: nothing
 * here belongs to an organization. Versions and the environment are platform
 * detail, so the call is held to the same `platform` ability as the admin area.
 */
#[IsReadOnly]
final class AppInfo extends Tool
{
    use AuthorizesToolCall;

    public const array PACKAGES = [
        'laravel/framework',
        'laravel/ai',
        'laravel/mcp',
        'laravel/fortify',
        'laravel/reverb',
        'inertiajs/inertia-laravel',
        'pestphp/pest',
    ];

    protected string $description = 'Reports the application name, environment, URL, PHP version and key package versions.';

    /**
     * @throws AuthenticationException|AuthorizationException
     */
    public function handle(Request $request): Response
    {
        $user = $request->user();

        throw_unless($user instanceof User, AuthenticationException::class, 'This server answers a signed-in member only.');

        $this->authorizeFor($user, 'platform', []);

        $lines = [
            'Application: '.config()->string('app.name'),
            'Environment: '.app()->environment(),
            'URL: '.config()->string('app.url'),
            'PHP: '.PHP_VERSION,
            'Laravel: '.Application::VERSION,
        ];

        foreach (self::PACKAGES as $package) {
            $version = InstalledVersions::isInstalled($package) ? InstalledVersions::getPrettyVersion($package) : null;

            if ($version !== null) {
                $lines[] = $package.': '.$version;
            }
        }

        return Response::text(implode("\n", $lines));
    }
}
