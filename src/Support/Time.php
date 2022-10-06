<?php

namespace Src\Support;

class Time
{
    private static array $start = [];
    private static float|null $update = null;

    public static function up(float $seconds, string $prefix, bool $first = false): bool
    {
        if (!isset(self::$start[$prefix])) {
            self::$start[$prefix] = microtime(true) + $seconds;

            if ($first)
                return true;
        }

        if (microtime(true) >= self::$start[$prefix]) {
            unset(self::$start[$prefix]);

            if ($first)
                return false;

            return true;
        }

        return false;
    }

    public static function reset(): void
    {
        self::$start = [];
        self::$update = null;
    }

    public static function update(float|null $update = null): void
    {
        $now = microtime(true);

        if ($update && empty(self::$update))
            self::$update = $now + $update;

        if (empty(self::$update) || $now > self::$update) {
            foreach (self::$start as $pr => $item)
                if ($now >= $item)
                    unset(self::$start[$pr]);

            self::$update = null;
        }
    }
}