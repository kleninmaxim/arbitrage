<?php

namespace Src\Services\Orderbook;

interface OrderbookAdapter
{
    public function recordOrderbook(string $exchange, array $orderbooks): void;
    public function getOrderbook(array|string $symbols = [], array|string $exchanges = [], array $list = []): mixed;
}