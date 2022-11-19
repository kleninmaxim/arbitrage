<?php

use Src\Crypto\Exchanges\Original\Binance\WebsocketMarketStream;
use Src\Support\Config;
use Src\Support\Log;
use Src\Support\Time;
use function Ratchet\Client\connect;

require_once dirname(__DIR__, 3) . '/index.php';

connect(WebsocketMarketStream::WEBSOCKET_ENDPOINT)->then(function ($conn) {
    // ZERO
    $binance_websocket = WebsocketMarketStream::init();
    // ZERO

    // CONFIG
    $exchange = $binance_websocket->getName();
    $config = Config::file('services_orderbooks', 'watchers')[$exchange];
    $markets = $config['markets'];
    // CONFIG

    // COUNT NECESSARY INFO
    $memcached = \Src\Databases\Memcached::init();
    // COUNT NECESSARY INFO

    // LOGIN AND SUBSCRIBE
    $conn->send($binance_websocket->messageRequestToSubscribeOrderbooks($markets));
    // LOGIN AND SUBSCRIBE

    $conn->on('message', function ($msg) use ($binance_websocket, $memcached, $exchange) {
        try {
            if ($msg !== null) {
                $data = $binance_websocket->processWebsocketData(json_decode($msg, true));

                if ($data['response'] == 'isOrderbook') {
                    $memcached->set($exchange . '_' . $data['data']['symbol'], $data['data']);

                    if (Time::up(60, 'get_orderbook_' . $data['data']['symbol'], true))
                        echo '[' . date('Y-m-d H:i:s') . '] [INFO] Get orderbook: '. $data['data']['symbol'] . PHP_EOL;
                } elseif ($data['response'] == 'isResult') {
                    echo '[' . date('Y-m-d H:i:s') . '] The request sent was a successful' . PHP_EOL;
                } else
                    Log::warning(['message' => 'Unexpected data get from websocket', 'file' => __FILE__, '$data' => $data]);
            } else {
                echo '[' . date('Y-m-d H:i:s') . '] Websocket mirror_trades get null from onMessage' . PHP_EOL;
                Log::warning(['message' => 'Websocket mirror_trades get null from onMessage', 'file' => __FILE__]);
            }
        } catch (Exception $e) {
            Log::error($e);
            throw new Exception();
        }
    });

    $conn->on('close', function($code = null, $reason = null) {
        echo '[' . date('Y-m-d H:i:s') . '] Connection closed. Code: ' . $code . '; Reason: ' . $reason . PHP_EOL;
    });
}, function (Exception $e) {
    echo "Could not connect: {$e->getMessage()}" . PHP_EOL;
});