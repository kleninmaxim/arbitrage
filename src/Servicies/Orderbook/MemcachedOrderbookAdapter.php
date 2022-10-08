<?php

namespace Src\Servicies\Orderbook;

use Src\Databases\Memcached;

class MemcachedOrderbookAdapter implements OrderbookAdapter
{
    private Memcached $memcached;

    public function __construct(Memcached $memcached)
    {
        $this->memcached = $memcached;
    }

    public static function init(...$parameters): static
    {
        return new static(Memcached::init(...$parameters));
    }

    public function record(string $exchange, array $orderbooks): void
    {
        $keys = isset($orderbooks['symbol'])
            ? $exchange . '_' . $orderbooks['symbol']
            : array_map(fn($orderbook) => $exchange . '_' . $orderbook['symbol'], $orderbooks);

        $this->memcached->set(
            $keys,
            $orderbooks
        );
    }

    public function get(array|string $symbols = [], array|string $exchanges = [], array $list = []): mixed
    {
        if ($list)
            return $this->memcached->get($list);

        foreach ($exchanges as $exchange)
            foreach ($symbols as $symbol)
                $keys[] = $exchange . '_' . $symbol;

        return $this->memcached->get($keys ?? []);
    }
}