<?php

use Src\Support\Config;
use Src\Support\Log;
use Src\Support\Time;
use function Ratchet\Client\connect;

require_once dirname(__DIR__, 3) . '/index.php';

connect('wss://ws-api.exmo.com:443/v1/public')->then(function ($conn) {
    // CONFIG
    $exchange = 'exmo';
    $config = Config::file('services_orderbooks', 'watchers')[$exchange];
    $markets = $config['markets'];
    // CONFIG

    // COUNT NECESSARY INFO
    $memcached = \Src\Databases\Memcached::init();
    // COUNT NECESSARY INFO

    // LOGIN AND SUBSCRIBE
    $conn->send(json_encode([
        'method' => 'subscribe',
        'id' => 1,
        'topics' => array_map(fn($market) => 'spot/order_book_snapshots:' . str_replace('/', '_', $market), $markets)
    ]));
    // LOGIN AND SUBSCRIBE

    $conn->on('message', function ($msg) use ($memcached, $exchange) {
        try {
            if ($msg !== null) {
                $data = processWebsocketData(json_decode($msg, true), ['exchange' => $exchange]);

                if ($data['response'] == 'isOrderbook') {
                    $memcached->set($exchange . '_' . $data['data']['symbol'], $data['data']);

                    if (Time::up(60, 'get_orderbook_' . $data['data']['symbol'], true))
                        echo '[' . date('Y-m-d H:i:s') . '] [INFO] Get orderbook: '. $data['data']['symbol'] . PHP_EOL;
                } elseif ($data['response'] == 'isConnectionEstablished') {
                    echo '[' . date('Y-m-d H:i:s') . '] [INFO] Connection is established with session id: ' . $data['data']['session_id'] . PHP_EOL;
                } elseif ($data['response'] == 'isSubscribed') {
                    echo '[' . date('Y-m-d H:i:s') . '] [INFO] Topic: ' . $data['data']['topic'] . ' is ' . $data['data']['event'] . PHP_EOL;
                } else {
                    Log::warning(['message' => 'Unexpected data get from websocket', 'file' => __FILE__, '$data' => $data]);
                }
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

/**
 * @throws Exception
 */
function processWebsocketData(mixed $data, array $options = []): array
{
    if (!empty($options['exchange']))
        if ($is = isOrderbook($data, $options['exchange']))
            return $is;

    if ($is = isSubscribed($data))
        return $is;

    if ($is = isConnectionEstablished($data))
        return $is;

    return ['response' => 'error', 'data' => null];
}

function isOrderbook($data, $exchange): array
{
    if (!empty($data['ts']) && !empty($data['event']) && !empty($data['data']) && !empty($data['topic'] && str_contains($data['topic'], 'spot/order_book_snapshots:'))) {
        if (!empty($data['data']['bid']) && !empty($data['data']['ask']) && $data['event'] == 'update') {
            foreach ($data['data']['bid'] as $key => $datum)
                unset($data['data']['bid'][$key][2]);

            foreach ($data['data']['ask'] as $key => $datum)
                unset($data['data']['ask'][$key][2]);

            return [
                'response' => 'isOrderbook',
                'data' => [
                    'symbol' => str_replace('_', '/', str_replace('spot/order_book_snapshots:', '', $data['topic'])),
                    'bids' => $data['data']['bid'],
                    'asks' => $data['data']['ask'],
                    'timestamp' => $data['ts'],
                    'datetime' => date('Y-m-d H:i:s', floor($data['ts'] / 1000)),
                    'nonce' => null,
                    'exchange' => $exchange
                ]
            ];
        }
    }

    return [];
}

function isSubscribed($data): array
{
    if (
        !empty($data['ts']) &&
        !empty($data['event']) &&
        !empty($data['id']) &&
        !empty($data['topic']) &&
        $data['event'] == 'subscribed'
    ) {
        $data['datetime'] = date('Y-m-d H:i:s', floor($data['ts'] / 1000));

        return ['response' => 'isSubscribed', 'data' => $data];
    }

    return [];
}

/**
 * @throws Exception
 */
function isConnectionEstablished($data): array
{
    if (
        !empty($data['ts']) &&
        !empty($data['event']) &&
        !empty($data['code']) &&
        !empty($data['message']) &&
        !empty($data['session_id'])
    ) {
        if ($data['event'] == 'info' && $data['code'] == 1 && $data['message'] == 'connection established') {
            $data['datetime'] = date('Y-m-d H:i:s', floor($data['ts'] / 1000));

            return ['response' => 'isConnectionEstablished', 'data' => $data];
        }

        throw new Exception('Connect was unsuccessful');
    }

    return [];
}