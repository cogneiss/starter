<?php

declare(strict_types=1);

use App\Webhooks\NativeHostnameResolver;

it('returns a literal ip address without a lookup', function (): void {
    expect(new NativeHostnameResolver()->resolve('127.0.0.1'))->toBe(['127.0.0.1']);
});

it('resolves a hostname through the system resolver', function (): void {
    // `localhost` comes from the hosts file, so the test never touches the network.
    expect(new NativeHostnameResolver()->resolve('localhost'))->toContain('127.0.0.1');
});

it('returns nothing for a name that cannot resolve', function (): void {
    // A space is invalid in a hostname, so resolution fails without a DNS query.
    expect(new NativeHostnameResolver()->resolve('name with spaces.invalid'))->toBe([]);
});
