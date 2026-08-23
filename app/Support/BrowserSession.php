<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The browser sessions a user currently has open. Reads the `sessions` table
 * directly, so it only has anything to say while the database session driver
 * is in use — any other driver keeps its sessions somewhere unlistable.
 */
final readonly class BrowserSession
{
    /**
     * @return array<int, array{id: string, device: string, ip_address: string|null, last_active_diff: string, current: bool}>
     */
    public static function forUser(User $user, string $currentSessionId): array
    {
        if (config()->string('session.driver') !== 'database') {
            return [];
        }

        return DB::table(config()->string('session.table', 'sessions'))
            ->where('user_id', $user->id)
            ->orderByDesc('last_activity')
            ->get()
            ->map(fn (object $session): array => [
                'id' => is_string($session->id) ? $session->id : '',
                'device' => self::device(is_string($session->user_agent) ? $session->user_agent : null),
                'ip_address' => is_string($session->ip_address) ? $session->ip_address : null,
                'last_active_diff' => now()->setTimestamp(is_numeric($session->last_activity) ? (int) $session->last_activity : 0)->diffForHumans(),
                'current' => $session->id === $currentSessionId,
            ])
            ->all();
    }

    /**
     * A human label for a user agent. Good enough to recognise your own
     * laptop next to a session you do not recognise; not a device fingerprint.
     */
    public static function device(?string $userAgent): string
    {
        $agent = $userAgent ?? '';

        $browser = match (true) {
            str_contains($agent, 'Edg') => 'Edge',
            str_contains($agent, 'OPR') => 'Opera',
            str_contains($agent, 'Firefox') => 'Firefox',
            str_contains($agent, 'Chrome') => 'Chrome',
            str_contains($agent, 'Safari') => 'Safari',
            default => 'Unknown browser',
        };

        $platform = match (true) {
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'iPhone'), str_contains($agent, 'iPad') => 'iOS',
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'Mac OS') => 'macOS',
            str_contains($agent, 'Linux') => 'Linux',
            default => 'unknown platform',
        };

        return $browser.' on '.$platform;
    }
}
