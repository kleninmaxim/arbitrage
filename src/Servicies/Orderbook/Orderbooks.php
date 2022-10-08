<?php

namespace Src\Servicies\Orderbook;

class Orderbooks
{
    private OrderbookAdapter $orderbook_recorder;

    public function __construct(OrderbookAdapter $orderbook_recorder)
    {
        $this->orderbook_recorder = $orderbook_recorder;
    }

    public function record(string $exchange, array $orderbooks): void
    {
        $this->orderbook_recorder->record($exchange, $orderbooks);
    }

    public function get(array|string $symbols = [], array|string $exchanges = [], array $list = []): mixed
    {
        return $this->orderbook_recorder->get($symbols, $exchanges, $list);
    }
}