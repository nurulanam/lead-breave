<?php

namespace App\Services\Website;

use Illuminate\Support\Facades\Cache;

/**
 * Where a lead's website check has got to, for the live progress display.
 * Kept in the cache for a short while; nothing permanent depends on it.
 */
class CheckProgress
{
    public const QUEUED = 'queued';

    public const RUNNING = 'running';

    public const DONE = 'done';

    public const FAILED = 'failed';

    public const WAITING_FOR_WORKER = 'Waiting for a worker…';

    private const TTL_MINUTES = 15;

    private const ACTIVITY_KEY = 'website-check-progress:last-activity';

    public static function queued(int $leadId): void
    {
        self::put($leadId, self::QUEUED, 0, self::WAITING_FOR_WORKER);
    }

    public static function step(int $leadId, int $percent, string $step): void
    {
        self::put($leadId, self::RUNNING, $percent, $step);
    }

    public static function waiting(int $leadId, string $reason): void
    {
        self::put($leadId, self::QUEUED, 0, $reason);
    }

    public static function done(int $leadId, string $summary): void
    {
        self::put($leadId, self::DONE, 100, $summary);
    }

    public static function failed(int $leadId, string $reason): void
    {
        self::put($leadId, self::FAILED, 100, $reason);
    }

    /**
     * @return array{state: string, percent: int, step: string, updated_at: int}|null
     */
    public static function get(int $leadId): ?array
    {
        $progress = Cache::get(self::key($leadId));

        return is_array($progress) ? $progress : null;
    }

    public static function isActive(int $leadId): bool
    {
        return in_array(self::get($leadId)['state'] ?? null, [self::QUEUED, self::RUNNING], true);
    }

    /**
     * Whether any website check was queued or moved on in the last few
     * minutes, so lists know to keep refreshing until the last one lands.
     */
    public static function recentlyActive(int $seconds = 120): bool
    {
        $lastActivity = Cache::get(self::ACTIVITY_KEY);

        return is_int($lastActivity) && $lastActivity >= now()->getTimestamp() - $seconds;
    }

    public static function forget(int $leadId): void
    {
        Cache::forget(self::key($leadId));
    }

    private static function put(int $leadId, string $state, int $percent, string $step): void
    {
        Cache::put(self::key($leadId), [
            'state' => $state,
            'percent' => max(0, min(100, $percent)),
            'step' => $step,
            'updated_at' => now()->getTimestamp(),
        ], now()->addMinutes(self::TTL_MINUTES));

        Cache::put(self::ACTIVITY_KEY, now()->getTimestamp(), now()->addMinutes(self::TTL_MINUTES));
    }

    private static function key(int $leadId): string
    {
        return "website-check-progress:{$leadId}";
    }
}
