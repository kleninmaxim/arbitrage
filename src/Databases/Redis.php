<?php

namespace Src\Databases;

use RedisException;
use Src\Support\Log;

class Redis
{
    private \Redis $redis;

    /**
     * @throws RedisException
     */
    public function __construct(\Redis $redis, string $service = '127.0.0.1', int $port = 6379)
    {
        $this->redis = $redis;
        $this->redis->connect($service, $port);
    }

    /**
     * @throws RedisException
     */
    public static function init(...$parameters): static
    {
        return new static(new \Redis(), ...$parameters);
    }

    public function queue(string $queueName, mixed $data): mixed
    {
        try {
            return $this->redis->rawCommand('RPUSH', $queueName, serialize($data));
        } catch (RedisException $e) {
            Log::error($e, ['$queueName' => $queueName, '$data' => $data]);
        }
        return null;
    }

    public function get(string $queueName): mixed
    {
        try {
            $data = $this->redis->rawCommand('LPOP', $queueName);
        } catch (RedisException $e) {
            Log::error($e, ['$queueName' => $queueName]);
        }
        return is_string($data) ? unserialize($data) : $data;
    }

    public function getLen(string $queueName): ?int
    {
        try {
            return $this->redis->rawCommand('LLEN', $queueName);
        } catch (RedisException $e) {
            Log::error($e, ['$queueName' => $queueName]);
        }
        return null;
    }

    public function flushAll(): bool|\Redis|null
    {
        try {
            return $this->redis->flushAll();
        } catch (RedisException $e) {
            Log::error($e, ['$queueName' => $queueName]);
        }
        return null;
    }
}