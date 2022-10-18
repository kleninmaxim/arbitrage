<?php

namespace Src\Services\Orderbook;

interface OrderbookWatcher
{
    public function watchOrderbook(OrderbookWorker $orderbook, string $method): void;
}