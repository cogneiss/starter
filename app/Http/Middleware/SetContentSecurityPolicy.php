<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * A per-request nonce with an enforcing policy: no unsafe-inline and no
 * unsafe-eval anywhere. The nonce is handed to Vite before the view renders,
 * so its injected tags and the layout's own inline blocks all carry it.
 */
final readonly class SetContentSecurityPolicy
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! config()->boolean('security.csp.enabled')) {
            return $next($request);
        }

        $nonce = Vite::useCspNonce();

        $response = $next($request);

        $header = config()->boolean('security.csp.report_only')
            ? 'Content-Security-Policy-Report-Only'
            : 'Content-Security-Policy';

        $response->headers->set($header, $this->policy($nonce));

        return $response;
    }

    private function policy(string $nonce): string
    {
        $scripts = ["'self'", "'nonce-{$nonce}'"];
        $styles = ["'self'", "'nonce-{$nonce}'", 'https://fonts.bunny.net'];
        $connect = ["'self'", ...$this->reverbOrigins(), ...array_values(array_filter(config()->array('security.csp.connect'), is_string(...)))];

        // The Vite dev server serves the modules and holds the HMR websocket
        // open, so its origin joins the policy only while it is running.
        if (Vite::isRunningHot()) {
            $dev = mb_rtrim((string) file_get_contents(Vite::hotFile()), '/');

            $scripts[] = $dev;
            $styles[] = $dev;
            $connect[] = $dev;
            $connect[] = (string) preg_replace('/^http/', 'ws', $dev);
        }

        return implode('; ', [
            "default-src 'self'",
            'script-src '.implode(' ', $scripts),
            'style-src '.implode(' ', $styles),
            "font-src 'self' https://fonts.bunny.net data:",
            "img-src 'self' data: blob:",
            'connect-src '.implode(' ', $connect),
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
        ]);
    }

    /**
     * @return list<string>
     */
    private function reverbOrigins(): array
    {
        $options = config()->array('broadcasting.connections.reverb.options');

        $host = $options['host'] ?? null;

        if (! is_string($host) || $host === '') {
            return [];
        }

        $protocol = ($options['scheme'] ?? 'https') === 'https' ? 'wss' : 'ws';
        $port = $options['port'] ?? 443;

        return [sprintf('%s://%s:%s', $protocol, $host, is_scalar($port) ? (string) $port : '443')];
    }
}
