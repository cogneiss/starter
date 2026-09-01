<?php

declare(strict_types=1);

namespace App\Webhooks;

final class NativeHostnameResolver implements ResolvesHostnames
{
    /**
     * @return list<string>
     */
    public function resolve(string $hostname): array
    {
        if (filter_var($hostname, FILTER_VALIDATE_IP) !== false) {
            return [$hostname];
        }

        $addresses = gethostbynamel($hostname);

        return $addresses === false ? [] : array_values($addresses);
    }
}
