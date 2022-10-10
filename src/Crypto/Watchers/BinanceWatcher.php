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
    private string $service_name;

    const WEBSOCKET = 'websocket';

    /**
     * @throws Exception
     */
    public function __construct(Binance $binance, string $service_name, ...$parameters)
    {
        $this->binance = $binance;
        $this->service_name = $service_name;

        $this->all_streams['partial_book_depth_stream'] = $this->binance->getPartialBookDepthStream(...$parameters);
    }

    /**
     * @throws Exception
     */
    public static function init(string $service_name, ...$parameters): static
    {
        return new static(new Binance(),$service_name,  ...$parameters);
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
    public function websocket(Orderbook $orderbook)
    {
        $streams = $this->binance->getStreamsWithOptions($this->all_streams);

        $websocket = Websocket::init($this->binance->getWebsocketEndpoint());
        $websocket->send($this->binance->getRequest($this->all_streams));

        while (true) {
            $data = $websocket->receive();

            try {
                $process_data = $this->binance->processWebsocketData($data, $streams);

                if ($process_data['response'] == 'orderbook') {
                    $orderbook->recordOrderbook(
                        $this->service_name,
                        $this->binance->getName(),
                        $process_data['data']
                    );
                } elseif ($process_data['response'] == 'result')
                    echo '[' . date('Y-m-d H:i:s') . '] The request sent was a successful' . PHP_EOL;
            } catch (Exception $e) {
                $orderbook->recordOrderbook(
                    $this->service_name,
                    $this->binance->getName(),
                    []
                );

                Log::error($e, $data);
                throw $e;
            }
        }
    }
}