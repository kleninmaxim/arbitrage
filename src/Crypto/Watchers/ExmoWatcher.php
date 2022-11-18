<?php

namespace Src\Crypto\Watchers;

use Exception;
use Src\Crypto\Exchanges\Original\Exmo;
use Src\Services\Orderbook\OrderbookWorker;
use Src\Services\Orderbook\OrderbookWatcher;
use Src\Support\Log;
use Src\Support\Time;
use Src\Support\Websocket;

class ExmoWatcher implements OrderbookWatcher
{
    private Exmo $exmo;
    private array $all_streams;
    private string $service_name;

    const WEBSOCKET = 'websocket';

    /**
     * @throws Exception
     */
    public function __construct(Exmo $exmo, string $service_name, ...$parameters)
    {
        $this->exmo = $exmo;
        $this->service_name = $service_name;

        $this->all_streams = ['order_book_snapshots' => $this->exmo->OrderBookSnapshots(...$parameters)];
    }

    /**
     * @throws Exception
     */
    public static function init(string $service_name, ...$parameters): static
    {
        return new static(new Exmo(), $service_name, ...$parameters);
    }

    /**
     * @throws Exception
     */
    public function watchOrderbook(OrderbookWorker $orderbook, string $method): void
    {
        $this->$method($orderbook);

        throw new Exception('Does not have such method: ' . $method);
    }

    /**
     * @throws Exception
     */
    public function websocket(OrderbookWorker $orderbook)
    {
        $streams = $this->exmo->getStreamsWithOptions($this->all_streams);

        $websocket = Websocket::init($this->exmo->getWebsocketEndpoint());
        $websocket->send($this->exmo->getRequest($this->all_streams));

        while (true) {
            try {
                if ($data = $websocket->receive()) {
                    $process_data = $this->exmo->processWebsocketData($data, $streams);

                    if ($process_data['response'] == 'orderbook') {
                        $orderbook->recordOrderbook(
                            $this->service_name,
                            $this->exmo->getName(),
                            $process_data['data']
                        );

                        if (Time::up(60, 'get_orderbook', true))
                            echo '[' . date('Y-m-d H:i:s') . '] [INFO] Get orderbook' . PHP_EOL;
                    } elseif ($process_data['response'] == 'greeting_message') {
                        echo '[' . date('Y-m-d H:i:s') . '] Connection is established with session id: ' . $process_data['data']['session_id'] . PHP_EOL;
                    } elseif ($process_data['response'] == 'response') {
                        echo '[' . date('Y-m-d H:i:s') . '] Topic: ' . $process_data['data']['topic'] . ' is ' . $process_data['data']['event'] . PHP_EOL;
                    }
                }
            } catch (Exception $e) {
                $orderbook->recordOrderbook(
                    $this->service_name,
                    $this->exmo->getName(),
                    []
                );

                Log::error($e, $data ?? []);
                throw $e;
            }
        }
    }
}