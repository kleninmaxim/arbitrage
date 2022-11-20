<?php

use Src\Crypto\Exchanges\Original\Exmo\WebsocketV1PublicApi;
use Src\Support\Config;
use Src\Support\Log;
use Src\Support\Time;
use function Ratchet\Client\connect;

require_once dirname(__DIR__, 3) . '/index.php';

connect(WebsocketV1PublicApi::WEBSOCKET_ENDPOINT)->then(function ($conn) {
    // ZERO
    $exmo_websocket = WebsocketV1PublicApi::init();
    // ZERO

    // CONFIG
    $exchange = $exmo_websocket->getName();
    $config = Config::file('services_orderbooks', 'watchers')[$exchange];
    $markets = $config['markets'];
    // CONFIG

    // COUNT NECESSARY INFO
    $memcached = \Src\Databases\Memcached::init();
    // COUNT NECESSARY INFO

    // LOGIN AND SUBSCRIBE
    $conn->send($exmo_websocket->messageRequestToSubscribeOrderbooks($markets));
    // LOGIN AND SUBSCRIBE

    $conn->on('message', function ($msg) use ($exmo_websocket, $memcached, $exchange) {
        try {
            if ($msg !== null) {
                $data = $exmo_websocket->processWebsocketData(json_decode($msg, true));

                if ($data['response'] == 'isOrderbook') {
                    $memcached->set($exchange . '_' . $data['data']['symbol'], $data['data']);
                    print_r($data['data']);
                    echo PHP_EOL;

                    if (Time::up(60, 'get_orderbook_' . $data['data']['symbol'], true))
                        echo '[' . date('Y-m-d H:i:s') . '] [INFO] Get orderbook: '. $data['data']['symbol'] . PHP_EOL;
                } elseif ($data['response'] == 'isConnectionEstablished') {
                    echo '[' . date('Y-m-d H:i:s') . '] [INFO] Connection is established with session id: ' . $data['data']['session_id'] . PHP_EOL;
                } elseif ($data['response'] == 'isSubscribed') {
                    echo '[' . date('Y-m-d H:i:s') . '] [INFO] Topic: ' . $data['data']['topic'] . ' is ' . $data['data']['event'] . PHP_EOL;
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
    echo '[' . date('Y-m-d H:i:s') . '] [ERROR] Could not connect: ' . $e->getMessage() . PHP_EOL;
});