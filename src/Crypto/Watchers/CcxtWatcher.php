<?php

namespace Src\Crypto\Watchers;

use Exception;
use Src\Crypto\Ccxt;
use Src\Services\Orderbook\Orderbook;
use Src\Services\Orderbook\OrderbookWatcher;
use Src\Support\Log;

class CcxtWatcher implements OrderbookWatcher
{
    private Ccxt $ccxt;
    private int $usleep;
    private string $symbol;
    private int $depth;
    private string $service_name;

    const REST = 'rest';

    public function __construct(Ccxt $ccxt, string $service_name, string $symbol, int $depth = 5, int $usleep = 1000000)
    {
        $this->ccxt = $ccxt;
        $this->symbol = $symbol;
        $this->usleep = $usleep;
        $this->depth = $depth;
        $this->service_name = $service_name;
    }

    public static function init(string $exchange, string $service_name, string $symbol, int $depth = 5, int $usleep = 0, ...$parameters): static
    {
        return new static(Ccxt::init($exchange, ...$parameters), $service_name, $symbol, $depth, $usleep);
    }

    /**
     * @throws Exception
     */
    public function watchOrderbook(Orderbook $orderbook, string $method): void
    {
        $this->$method($orderbook);

        throw new Exception('Does not have such method: ' . $method);
    }

    /**
     * @throws Exception
     */
    public function rest(Orderbook $orderbook)
    {
        while (true) {
            usleep($this->usleep);

            try {
                $orderbook->recordOrderbook(
                    $this->service_name,
                    $this->ccxt->name,
                    $this->ccxt->getOrderBook($this->symbol, $this->depth)
                );
            } catch (Exception $e) {
                $orderbook->recordOrderbook(
                    $this->service_name,
                    $this->ccxt->name,
                    []
                );

                Log::error($e);
                throw $e;
            }
        }
    }
}