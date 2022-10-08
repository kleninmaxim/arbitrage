<?php

namespace Src\Databases;

class Memcached
{
    private \Memcached $memcached;
    private string $prefix = '';

    public function __construct(\Memcached $memcached, array $services = ['127.0.0.1'])
    {
        $this->memcached = $memcached;

        foreach ($services as $service)
            $this->memcached->addServer($service, 11211);
    }

    public static function initWebsocket(...$parameters): static
    {
        return new static(new \Memcached(), ...$parameters);
    }

    public function getData(array|string $keys): mixed
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

    public function set($key, $value): bool
    {
        return $this->memcached->set($this->prefix . $key, ['data' => $value, 'timestamp' => microtime(true)]);
    }

    public function setPrefix($prefix): void
    {
        $this->prefix = $prefix . '_';
    }
}