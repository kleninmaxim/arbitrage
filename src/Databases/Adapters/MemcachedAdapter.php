<?php

namespace Src\Databases\Adapters;

use Src\Databases\Memcached;
use Src\Services\Orderbook\OrderbookAdapter;

class MemcachedAdapter implements OrderbookAdapter
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

    public function recordOrderbook(string $service, string $exchange, array $orderbooks): void
    {
        if (isset($orderbooks['symbol'])) {
            $keys = $exchange . '_' . $orderbooks['symbol'];
            $data = [
                'service' => $service,
                'orderbook' => $orderbooks
            ];
        } else {
            foreach ($orderbooks as $orderbook) {
                $keys[] = $exchange . '_' . $orderbook['symbol'];
                $data[] = [
                    'service' => $service,
                    'orderbook' => $orderbook
                ];
            }
        }

        $this->memcached->set(
            $keys ?? [],
            $data ?? []
        );
    }

    public function getOrderbook(array|string $symbols = [], array|string $exchanges = [], array $list = []): mixed
    {
        if ($list)
            return $this->memcached->get($list);
        if (is_array($exchanges) && is_array($symbols)) {
            foreach ($exchanges as $exchange)
                foreach ($symbols as $symbol)
                    $keys[] = $exchange . '_' . $symbol;
        } elseif (is_array($exchanges) && is_string($symbols)) {
            foreach ($exchanges as $exchange)
                $keys[] = $exchange . '_' . $symbols;
        } elseif (is_string($exchanges) && is_array($symbols)) {
            foreach ($symbols as $symbol)
                $keys[] = $exchanges . '_' . $symbol;
        }elseif (is_string($exchanges) && is_string($symbols)) {
            $keys = $exchanges . '_' . $symbols;
        }

        return $this->memcached->get($keys ?? []);
    }
}