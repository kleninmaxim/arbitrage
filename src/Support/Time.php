<?php

namespace Src\Support;

class Time
{
    private static array $start = [];

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
    }

    public static function update(array $except = []): void
    {
        $now = microtime(true);

        foreach (self::$start as $prefix => $microtime)
            if ($now >= $microtime && !in_array($prefix, $except))
                unset(self::$start[$prefix]);
    }

    public static function get(): array
    {
        return self::$start;
    }
}