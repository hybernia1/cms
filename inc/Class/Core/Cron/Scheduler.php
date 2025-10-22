<?php
declare(strict_types=1);

namespace Core\Cron;

use DateInterval;
use DateTimeImmutable;
use InvalidArgumentException;

final class Scheduler
{
    /** @var callable():int */
    private $timeProvider;

    /**
     * @param callable():int|null $timeProvider
     */
    public function __construct(?callable $timeProvider = null)
    {
        $this->timeProvider = $timeProvider ?? static fn(): int => time();
    }

    public function now(): int
    {
        return (int)($this->timeProvider)();
    }

    public function intervalToSeconds(DateInterval|int|string $interval): ?int
    {
        if ($interval instanceof DateInterval) {
            return $this->dateIntervalToSeconds($interval);
        }

        if (is_int($interval)) {
            return $interval > 0 ? $interval : null;
        }

        $normalized = strtolower(trim($interval));
        if ($normalized === '' || $normalized === 'now' || $normalized === 'immediate' || $normalized === 'once') {
            return null;
        }

        if (is_numeric($normalized)) {
            $seconds = (int)$normalized;
            return $seconds > 0 ? $seconds : null;
        }

        if (str_starts_with($normalized, 'p')) {
            return $this->dateIntervalToSeconds(new DateInterval($interval));
        }

        $map = [
            'minute'      => 60,
            'minutely'    => 60,
            'hour'        => 3600,
            'hourly'      => 3600,
            'twicedaily'  => 12 * 3600,
            'daily'       => 86400,
            'day'         => 86400,
            'weekly'      => 7 * 86400,
            'week'        => 7 * 86400,
            'monthly'     => 30 * 86400,
        ];

        if (array_key_exists($normalized, $map)) {
            return $map[$normalized];
        }

        throw new InvalidArgumentException('Unsupported cron interval: ' . $interval);
    }

    public function nextRun(?int $intervalSeconds, ?int $from = null): int
    {
        $base = $from ?? $this->now();
        if ($intervalSeconds === null) {
            return $base;
        }

        return $base + $intervalSeconds;
    }

    private function dateIntervalToSeconds(DateInterval $interval): int
    {
        $origin = new DateTimeImmutable('@0');
        $target = $origin->add($interval);
        return (int)$target->format('U');
    }
}
