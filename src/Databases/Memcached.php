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

    public static function init(...$parameters): static
    {
        return new static(new \Memcached(), ...$parameters);
    }

    public function get(array|string $keys): mixed
    {
        if (is_array($keys))
            return $this->memcached->getMulti(
                array_map(
                    fn($key) => $this->setKeyFormat($key),
                    $keys
                )
            );

        return $this->memcached->get($this->setKeyFormat($keys));
    }

    public function set(array|string $keys, mixed $values): bool
    {
        if (is_array($keys) && is_array($values))
            return $this->memcached->setMulti(
                array_combine(
                    array_map(fn($key) => $this->setKeyFormat($key), $keys),
                    array_map(fn($value) => $this->setValueFormat($value), $values)
                )
            );

        if (is_string($keys))
            return $this->memcached->set(
                $this->setKeyFormat($keys),
                $this->setValueFormat($values)
            );

        return false;
    }

    public function setPrefix(string $prefix = ''): void
    {
        $this->prefix = $prefix;

        if ($prefix)
            $this->prefix .= '_';
    }

    private function setKeyFormat(string $key): string
    {
        return $this->prefix . $key;
    }

    private function setValueFormat(mixed $data): array
    {
        return ['data' => $data, 'timestamp' => microtime(true)];
    }
}