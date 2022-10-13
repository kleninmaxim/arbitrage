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
                    __CLASS__,
                    ['$key' => $key, 'service' => $datum['data']['service'], 'log_message' => 'Service has expired and not access Filter: ' . __CLASS__ . __METHOD__]
                );
            }

        return array_filter(
            $data,
            fn($datum) => in_array($datum['data']['service'], $accesses)
        );
    }
}