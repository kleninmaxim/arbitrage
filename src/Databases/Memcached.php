<?php

namespace Src\Databases;

class Memcached extends \Memcached
{
    private string $prefix = '';

    public function __construct(array $services = ['127.0.0.1'])
    {
        parent::__construct();

        foreach ($services as $service)
            $this->addServer($service, 11211);
    }

    public function getData(array|string $keys): mixed
    {
        if (is_array($keys))
            return $this->getMulti(
                array_map(
                    fn($key) => $this->prefix . $key,
                    $keys
                )
            );

        return $this->get($this->prefix . $keys);
    }

    /**
     * (PECL memcached &gt;= 0.1.0)<br/>
     * Store an item
     * @link https://php.net/manual/en/memcached.set.php
     * @param string $key <p>
     * The key under which to store the value.
     * </p>
     * @param mixed $value <p>
     * The value to store.
     * </p>
     * @param int $expiration [optional] <p>
     * The expiration time, defaults to 0. See Expiration Times for more info.
     * </p>
     * @param int $udf_flags [optional]
     * @return bool <b>TRUE</b> on success or <b>FALSE</b> on failure.
     * Use <b>Memcached::getResultCode</b> if necessary.
     */
    public function set($key, $value, $expiration = 0, $udf_flags = 0): bool
    {
        return parent::set($this->prefix . $key, ['data' => $value, 'timestamp' => microtime(true)], $expiration);
    }

    public function setPrefix($prefix): void
    {
        $this->prefix = $prefix . '_';
    }
}