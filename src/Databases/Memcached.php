<?php

namespace Src\Databases;

class Memcached
{
    private \Memcached $memcached;
    private string $prefix = '';

    public function __construct(array $services = ['127.0.0.1'])
    {
        $this->memcached = new \Memcached();

        foreach ($services as $service)
            $this->memcached->addServer($service, 11211);
    }

    public function get(array|string $keys)
    {
        if (is_array($keys))
            return $this->memcached->getMulti(
                array_map(
                    fn($key) => $this->prefix . $key,
                    $keys
                )
            );

        return $this->memcached->get($this->prefix . $keys);
    }

    public function set(string $key, mixed $data): void
    {
        $this->memcached->set($this->prefix . $key, [
            'data' => $data,
            'timestamp' => microtime(true)
        ]);
    }

    public function flushAll(): void
    {
        $this->memcached->flush();
    }

    public function setPrefix($prefix): void
    {
        $this->prefix = $prefix . '_';
    }
}