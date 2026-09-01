<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Vite;

it('ships an enforcing policy with a nonce and no inline escape hatches', function (): void {
    $response = $this->get(route('login'))->assertOk();

    $policy = $response->headers->get('Content-Security-Policy');

    expect($policy)->not->toBeNull()
        ->and($policy)->toContain("default-src 'self'")
        ->and($policy)->toContain("'nonce-")
        ->and($policy)->toContain("object-src 'none'")
        ->and($policy)->toContain("base-uri 'self'")
        ->and($policy)->toContain("form-action 'self'")
        ->and($policy)->toContain("frame-ancestors 'self'")
        ->and($policy)->not->toContain('unsafe-inline')
        ->and($policy)->not->toContain('unsafe-eval')
        ->and($response->headers->get('Content-Security-Policy-Report-Only'))->toBeNull();
});

it('stamps the nonce on the inline blocks and rotates it per request', function (): void {
    $first = $this->get(route('login'));
    $second = $this->get(route('login'));

    preg_match("/'nonce-([^']+)'/", (string) $first->headers->get('Content-Security-Policy'), $matches);

    expect($matches[1])->not->toBe('')
        ->and($first->getContent())->toContain('nonce="'.$matches[1].'"');

    preg_match("/'nonce-([^']+)'/", (string) $second->headers->get('Content-Security-Policy'), $other);

    expect($other[1])->not->toBe($matches[1]);
});

it('switches to report-only via config', function (): void {
    config(['security.csp.report_only' => true]);

    $response = $this->get(route('login'));

    expect($response->headers->get('Content-Security-Policy'))->toBeNull()
        ->and($response->headers->get('Content-Security-Policy-Report-Only'))->toContain("default-src 'self'");
});

it('can be disabled via config', function (): void {
    config(['security.csp.enabled' => false]);

    $response = $this->get(route('login'));

    expect($response->headers->get('Content-Security-Policy'))->toBeNull()
        ->and($response->headers->get('Content-Security-Policy-Report-Only'))->toBeNull();
});

it('covers the reverb websocket and configured hosts in connect-src', function (): void {
    config([
        'broadcasting.connections.reverb.options' => [
            'host' => 'ws.example.com',
            'port' => 443,
            'scheme' => 'https',
        ],
        'security.csp.connect' => ['https://eu.posthog.com'],
    ]);

    $policy = (string) $this->get(route('login'))->headers->get('Content-Security-Policy');

    preg_match('/connect-src ([^;]+)/', $policy, $matches);

    expect($matches[1])->toContain("'self'")
        ->and($matches[1])->toContain('wss://ws.example.com:443')
        ->and($matches[1])->toContain('https://eu.posthog.com');
});

it('leaves connect-src at self when no reverb host is configured', function (): void {
    config(['broadcasting.connections.reverb.options' => ['host' => null]]);

    $policy = (string) $this->get(route('login'))->headers->get('Content-Security-Policy');

    preg_match('/connect-src ([^;]+)/', $policy, $matches);

    expect(mb_trim($matches[1]))->toBe("'self'");
});

it('allows the vite dev server while running hot', function (): void {
    $hotFile = sys_get_temp_dir().'/csp-test-hot';
    file_put_contents($hotFile, 'http://localhost:5173/');

    Vite::useHotFile($hotFile);

    try {
        $policy = (string) $this->get(route('login'))->headers->get('Content-Security-Policy');
    } finally {
        unlink($hotFile);
    }

    preg_match('/script-src ([^;]+)/', $policy, $scripts);
    preg_match('/connect-src ([^;]+)/', $policy, $connect);

    expect($scripts[1])->toContain('http://localhost:5173')
        ->and($connect[1])->toContain('http://localhost:5173')
        ->and($connect[1])->toContain('ws://localhost:5173');
});
