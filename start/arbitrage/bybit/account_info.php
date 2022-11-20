<?php

use Src\Crypto\Exchanges\Original\Bybit\Spot;
use Src\Support\Config;
use Src\Support\Log;
use Src\Support\Time;
use function Ratchet\Client\connect;

require_once dirname(__DIR__, 3) . '/index.php';
require_once __DIR__ . '/helper/bybit_functions.php';

connect('wss://stream.bybit.com/spot/private/v3')->then(function ($conn) {
    // CONFIG
    $config = Config::config('arbitrage', 'bybit');

    $exchange = $config['exchange'];
    $assets = $config['assets'];
    $use_markets = $config['use_markets'];
    // CONFIG

    // API KEYS
    $api_keys_exchange = Config::file('keys', $exchange);
    // API KEYS

    // CCXT
    $ccxt_exchange = Spot::init($api_keys_exchange['api_public'], $api_keys_exchange['api_private'], ['symbols' => $use_markets]);
    // CCXT

    // COUNT NECESSARY INFO
    $memcached = \Src\Databases\Memcached::init();
    $redis = \Src\Databases\Redis::init();
    $markets = $ccxt_exchange->getMarketsWithOrigin();
    $balances = $ccxt_exchange->getBalances($assets);
    $memcached->set('accountInfo_' . $exchange, ['balances' => $balances]);
    // COUNT NECESSARY INFO

    foreach ($balances as $asset => $balance) {
        echo '[' . date('Y-m-d H:i:s') . '] [INFO] Balance update: ' . $asset . ', free: ' . $balance['free'] . ', used: ' . $balance['used'] . ', total: ' . $balance['total'] . PHP_EOL;
        $redis->queue('balances', ['exchange' => $exchange, 'asset' => $asset, 'balance' => $balance]);
    }

    // LOGIN AND SUBSCRIBE
    $expires = (time() + 3) * 1000;
    $conn->send(json_encode([
        'op' => 'auth',
        'args' => [$api_keys_exchange['api_public'], $expires, generateSignature($api_keys_exchange['api_private'], $expires)]
    ]));

    $conn->send(json_encode([
        'req_id' => 1,
        'op' => 'subscribe',
        'args' => ['outboundAccountInfo', 'order']
    ]));
    // LOGIN AND SUBSCRIBE

    $conn->on('message', function ($msg) use (&$conn, $memcached, $redis, $exchange, $markets, $assets) {
        if ($msg !== null) {
            $jsn_data = json_decode($msg, true);

            if (!isset($jsn_data['op']) || $jsn_data['op'] != 'pong') {
                $data = processWebsocketData(json_decode($msg, true), ['markets' => $markets, 'assets' => $assets]);

                if ($data['response'] == 'isUpdateOrSnapshotSpotUserWallet') {
                    // PRE COUNT
                    $memcached_key = 'accountInfo_' . $exchange;
                    $account_info = $memcached->get($memcached_key) ?: ['data' => ['balances' => []]];
                    // PRE COUNT

                    foreach ($data['data'] as $asset => $balance) {
                        $account_info['data']['balances'][$asset] = $balance;
                        echo '[' . date('Y-m-d H:i:s') . '] [INFO] Balance update: ' . $asset . ', free: ' . $balance['free'] . ', used: ' . $balance['used'] . ', total: ' . $balance['total'] . PHP_EOL;
                        $redis->queue('balances', ['exchange' => $exchange, 'asset' => $asset, 'balance' => $balance]);
                    }

                    // END COUNTING
                    $memcached->set($memcached_key, $account_info['data']);
                    // END COUNTING
                } elseif ($data['response'] == 'isUpdateOrSnapshotOrders') {
                    if ($order = $data['data']) {
                        // PRE COUNT
                        $memcached_key = 'accountInfo_' . $exchange;
                        $account_info = $memcached->get($memcached_key) ?: ['data' => ['open_orders' => []]];
                        // PRE COUNT

                        if ($order['status'] == 'open') {
                            $account_info['data']['open_orders'][$order['id']] = $order;
                        } else {
                            unset($account_info['data']['open_orders'][$order['id']]);
                        }

                        // END COUNTING
                        $memcached->set($memcached_key, $account_info['data']);
                        // END COUNTING

                        echo '[' . date('Y-m-d H:i:s') . '] [INFO] Order update: ' . $order['id'] . ', ' . $order['symbol'] . ', ' . $order['side'] . ', ' . $order['price'] . ', ' . $order['amount'] . ', ' . $order['status'] . PHP_EOL;

                        $order['exchange'] = $exchange;
                        $redis->queue('orders', $order);
                    }
                } elseif ($data['response'] == 'isLoggedIn') {
                    echo '[' . date('Y-m-d H:i:s') . '] [INFO] Successful logged in ' . $data['data']['conn_id']. PHP_EOL;
                } elseif ($data['response'] == 'isSubscribed') {
                    echo '[' . date('Y-m-d H:i:s') . '] [INFO] Conn: ' . $data['data']['conn_id'] . ' is ' . $data['data']['op'] . PHP_EOL;
                } else {
                    Log::warning(['message' => 'Unexpected data get from websocket', 'file' => __FILE__, '$data' => $data]);
                }
            }

            if (Time::up(3, 'orderbook'))
                $conn->send(json_encode([
                    'req_id' => 2,
                    'op' => 'ping'
                ]));
        } else {
            echo '[' . date('Y-m-d H:i:s') . '] Websocket mirror_trades get null from onMessage' . PHP_EOL;

            Log::warning(['message' => 'Websocket mirror_trades get null from onMessage', 'file' => __FILE__]);
        }
    });

    $conn->on('close', function($code = null, $reason = null) {
        echo '[' . date('Y-m-d H:i:s') . '] Connection closed. Code: ' . $code . '; Reason: ' . $reason . PHP_EOL;
        throw new Exception('[' . date('Y-m-d H:i:s') . '] Connection closed. Code: ' . $code . '; Reason: ' . $reason);
    });
}, function (Exception $e) {
    echo "Could not connect: {$e->getMessage()}" . PHP_EOL;
});