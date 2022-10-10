<?php

namespace Src\Support;

class Filter
{
    public static function memcachedDataByTimestamp(array $data, float $lifetime): array
    {
        $accesses = [];

        foreach ($data as $key => $datum)
            if ((microtime(true) - $datum['timestamp']) <= $lifetime) {
                if (!in_array($datum['data']['service'], $accesses))
                    $accesses[] = $datum['data']['service'];
            } elseif (Time::up(5, $key, true)) {
                Log::log(
                    str_replace('/', '_', $key),
                    ['$key' => $key, 'service' => $datum['data']['service']]
                );
            }

        return array_filter(
            $data,
            fn($datum) => in_array($datum['data']['service'], $accesses)
        );
    }
}