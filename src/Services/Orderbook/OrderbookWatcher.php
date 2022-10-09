<?php

namespace Src\Services\Orderbook;

interface OrderbookWatcher
{
    public function watchOrderbook(Orderbook $orderbook, string $method): void;
}