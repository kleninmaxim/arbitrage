<?php

namespace Src\Servicies\Orderbook;

interface OrderbookAdapter
{
    public function record(string $exchange, array $orderbooks): void;
    public function get(array|string $symbols = [], array|string $exchanges = [], array $list = []): mixed;
}