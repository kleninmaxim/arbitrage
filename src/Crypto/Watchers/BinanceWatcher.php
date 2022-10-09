<?php

namespace Src\Crypto\Watchers;

use Exception;
use Src\Crypto\Exchanges\Original\Binance;
use Src\Services\Orderbook\Orderbook;
use Src\Services\Orderbook\OrderbookWatcher;
use Src\Support\Log;
use Src\Support\Websocket;

class BinanceWatcher implements OrderbookWatcher
{
    private Binance $binance;
    private array $all_streams;

    const WEBSOCKET = 'websocket';

    /**
     * @throws Exception
     */
    public function __construct(Binance $binance, array|string $symbols = '', int $depth = 5, bool $fast = true, array $custom = [])
    {
        $this->binance = $binance;

        if ($custom) {
            $options = $custom;
        } elseif (is_string($symbols)) {
            $options = [['symbol' => $symbols, 'level' => $depth, 'fast' => $fast]];
        } elseif (is_array($symbols)) {
            foreach ($symbols as $symbol)
                $options[] = ['symbol' => $symbol, 'level' => $depth, 'fast' => $fast];
        }

        $this->all_streams = ['partial_book_depth_stream' => $options ?? []];
    }

    /**
     * @throws Exception
     */
    public static function init(...$parameters): static
    {
        return new static(new Binance(), ...$parameters);
    }

    /**
     * @throws Exception
     */
    public function watchOrderbook(Orderbook $orderbook, string $method): void
    {
        if ($method == self::WEBSOCKET) {
            $streams = $this->binance->getStreamsWithOptions($this->all_streams);

            $websocket = Websocket::init($this->binance->getWebsocketEndpoint());
            $websocket->send($this->binance->getRequest($this->all_streams));

            while (true) {
                $data = $websocket->receive();

                try {
                    $process_data = $this->binance->processWebsocketData($data, $streams);

                    if ($process_data['response'] == 'orderbook')
                        $orderbook->recordOrderbook(
                            $this->binance->getName(),
                            $process_data['data']
                        );
                } catch (Exception $e) {
                    Log::error($e, $data);

                    throw $e;
                }
            }
        }

        throw new Exception('Does not have such method: ' . $method);
    }
}