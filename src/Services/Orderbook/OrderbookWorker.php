<?php

namespace Src\Services\Orderbook;

use Exception;
use Src\Databases\Adapters\MemcachedAdapter;

class OrderbookWorker
{
    private OrderbookWatcher|null $watcher;
    private OrderbookAdapter $orderbook_recorder;

    public function __construct(OrderbookAdapter $orderbook_recorder, OrderbookWatcher $watcher = null)
    {
        $this->watcher = $watcher;
        $this->orderbook_recorder = $orderbook_recorder;
    }

    public static function init(OrderbookWatcher $watcher = null): static
    {
        return new static(MemcachedAdapter::init(), $watcher);
    }

    public function recordOrderbook(...$parameters): void
    {
        $this->orderbook_recorder->recordOrderbook(...$parameters);
    }

    public function getOrderbook(...$parameters): mixed
    {
        return $this->orderbook_recorder->getOrderbook(...$parameters);
    }

    /**
     * @throws Exception
     */
    public function watchOrderbook(string $method): void
    {
        if (!$this->watcher)
            throw new Exception('Watcher Orderbook is null');

        $this->watcher->watchOrderbook($this, $method);
    }
}