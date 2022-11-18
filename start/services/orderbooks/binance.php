<?php

use Src\Support\Config;
use Src\Support\Log;
use Src\Support\Time;
use function Ratchet\Client\connect;

require_once dirname(__DIR__, 3) . '/index.php';

connect('wss://stream.binance.com:9443/stream')->then(function ($conn) {
    // CONFIG
    $exchange = 'binance';
    $config = Config::file('services_orderbooks', 'watchers')[$exchange];
    $markets = $config['markets'];
    // CONFIG

    // COUNT NECESSARY INFO
    $memcached = \Src\Databases\Memcached::init();
    $original_markets = [];
    foreach ($markets as $market)
        $original_markets[mb_strtolower(str_replace('/', '', $market))] = $market;
    $stream = '@depth10@100ms';
    // COUNT NECESSARY INFO

    // LOGIN AND SUBSCRIBE
    $conn->send(json_encode([
        'method' => 'SUBSCRIBE',
        'params' => array_map(fn($market) => mb_strtolower(str_replace('/', '', $market)) . $stream, $markets),
        'id' => 1
    ]));
    // LOGIN AND SUBSCRIBE

    $conn->on('message', function ($msg) use ($memcached, $exchange, $original_markets, $stream) {
        try {
            if ($msg !== null) {
                $data = processWebsocketData(json_decode($msg, true), ['exchange' => $exchange, 'original_markets' => $original_markets, 'stream' => $stream]);

                if ($data['response'] == 'isOrderbook') {
                    $memcached->set($exchange . '_' . $data['data']['symbol'], $data['data']);

                    if (Time::up(60, 'get_orderbook_' . $data['data']['symbol'], true))
                        echo '[' . date('Y-m-d H:i:s') . '] [INFO] Get orderbook: '. $data['data']['symbol'] . PHP_EOL;
                } elseif ($data['response'] == 'isResult') {
                    echo '[' . date('Y-m-d H:i:s') . '] The request sent was a successful' . PHP_EOL;
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
    if (!empty($options['exchange']) && !empty($options['original_markets']) && !empty($options['stream']))
        if ($is = isOrderbook($data, $options['exchange'], $options['original_markets'], $options['stream']))
            return $is;

    if ($is = isResult($data))
        return $is;

    return ['response' => 'error', 'data' => null];
}

function isOrderbook($data, $exchange, $original_markets, $stream): array
{
    if (!empty($data['stream']) && !empty($data['data'])) {
        if (!empty($data['data']['bids']) && !empty($data['data']['asks']) && !empty($data['data']['lastUpdateId'])) {
            return [
                'response' => 'isOrderbook',
                'data' => [
                    'symbol' => $original_markets[str_replace($stream, '', $data['stream'])],
                    'bids' => $data['data']['bids'],
                    'asks' => $data['data']['asks'],
                    'timestamp' => null,
                    'datetime' => null,
                    'nonce' => $data['data']['lastUpdateId'],
                    'exchange' => $exchange
                ]
            ];
        }
    }

    return [];
}

/**
 * @throws Exception
 */
function isResult($data): array
{
    if (empty($data['result']) && !empty($data['id'])) {
        if (is_null($data['result']) && $data['id'] == 1)
            return ['response' => 'isResult', 'data' => null];

        throw new Exception('The request sent was unsuccessful');
    }

    return [];
}