<?php

namespace Src\Crypto\Watchers;

use Exception;
use Src\Crypto\Ccxt;
use Src\Services\Orderbook\Orderbook;
use Src\Services\Orderbook\OrderbookWatcher;

class CcxtWatcher implements OrderbookWatcher
{
    private Ccxt $ccxt;
    private int $usleep;
    private string $symbol;
    private int $depth;

    const REST = 'rest';

    public function __construct(Ccxt $ccxt, string $symbol, int $depth = 5, int $usleep = 1000000)
    {
        $this->ccxt = $ccxt;
        $this->symbol = $symbol;
        $this->usleep = $usleep;
        $this->depth = $depth;
    }

    public static function init(string $exchange, string $symbol, int $depth = 5, int $usleep = 0, ...$parameters): static
    {
        return new static(Ccxt::init($exchange, ...$parameters), $symbol, $depth, $usleep);
    }

    /**
     * @throws Exception
     */
    public function watchOrderbook(Orderbook $orderbook, string $method): void
    {
        $this->$method($orderbook);

        throw new Exception('Does not have such method: ' . $method);
    }

    public function rest(Orderbook $orderbook)
    {
        while (true) {
            usleep($this->usleep);

            $orderbook->recordOrderbook($this->ccxt->name, $this->ccxt->getOrderBook($this->symbol, $this->depth));
        }
    }
}