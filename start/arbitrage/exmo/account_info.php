<?php

use Src\Crypto\Ccxt;
use Src\Support\Config;
use Src\Support\Log;
use function Ratchet\Client\connect;

require_once dirname(__DIR__, 3) . '/index.php';
require_once __DIR__ . '/helper/exmo_functions.php';

$memcached = \Src\Databases\Memcached::init();

connect('wss://ws-api.exmo.com:443/v1/private')->then(function ($conn) {
    // CONFIG
    $config = Config::config('arbitrage', 'first');

    $exchange = $config['exchange'];
    $assets = $config['assets'];
    $use_markets = $config['use_markets'];
    // CONFIG

    // API KEYS
    $api_keys_exchange = Config::config('keys', $exchange, 'mirror_trades_key');
    // API KEYS

    // CCXT
    $ccxt_exchange = Ccxt::init($exchange);
    // CCXT

    // COUNT NECESSARY INFO
    $memcached = \Src\Databases\Memcached::init();
    $markets = originCcxtMarketIds($ccxt_exchange->getMarkets($assets), $use_markets);
    // COUNT NECESSARY INFO

    // LOGIN AND SUBSCRIBE
    $nonce = time();
    $conn->send(json_encode([
        'method' => 'login',
        'id' => 1,
        'api_key' => $api_keys_exchange['api_public'],
        'sign' => generateSignature($api_keys_exchange['api_public'], $api_keys_exchange['api_private'], $nonce),
        'nonce' => $nonce
    ]));

    $conn->send(json_encode([
        'method' => 'subscribe',
        'id' => 2,
        'topics' => [
            'spot/wallet',
            'spot/orders'
        ]
    ]));
    // LOGIN AND SUBSCRIBE

    $conn->on('message', function ($msg) use ($memcached, $exchange, $markets, $assets) {
        if ($msg !== null) {
            $data = processWebsocketData(json_decode($msg, true), ['markets' => $markets, 'assets' => $assets]);

            if ($data['response'] == 'isUpdateOrSnapshotSpotUserWallet') {
                // PRE COUNT
                $memcached_key = 'accountInfo_' . $exchange;
                $account_info = $memcached->get($memcached_key);
                // PRE COUNT

                foreach ($data['data'] as $asset => $balance) {
                    $account_info['data']['balances'][$asset] = $balance;
                    echo '[' . date('Y-m-d H:i:s') . '] [INFO] Balance update: ' . $asset . ', free: ' . $balance['free'] . ', used: ' . $balance['used'] . ', total: ' . $balance['total'] . PHP_EOL;
                }

                // END COUNTING
                $memcached->set($memcached_key, $account_info['data']);
                // END COUNTING
            } elseif ($data['response'] == 'isUpdateOrSnapshotOrders') {
                if ($order = $data['data']) {
                    // PRE COUNT
                    $memcached_key = 'accountInfo_' . $exchange;
                    $account_info = $memcached->get($memcached_key);
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
                }
            } elseif ($data['response'] == 'isConnectionEstablished') {
                echo '[' . date('Y-m-d H:i:s') . '] [INFO] Connection is established with session id: ' . $data['data']['session_id'] . PHP_EOL;
            } elseif ($data['response'] == 'isLoggedIn') {
                echo '[' . date('Y-m-d H:i:s') . '] [INFO] Successful logged in.' . PHP_EOL;
            } elseif ($data['response'] == 'isSubscribed') {
                echo '[' . date('Y-m-d H:i:s') . '] [INFO] Topic: ' . $data['data']['topic'] . ' is ' . $data['data']['event'] . PHP_EOL;
            } else {
                Log::warning(['message' => 'Unexpected data get from websocket', 'file' => __FILE__, '$data' => $data]);
            }
        } else {
            echo '[' . date('Y-m-d H:i:s') . '] Websocket mirror_trades get null from onMessage' . PHP_EOL;

            Log::warning(['message' => 'Websocket mirror_trades get null from onMessage', 'file' => __FILE__]);
        }
    });

    $conn->on('close', function($code = null, $reason = null) {
        echo '[' . date('Y-m-d H:i:s') . '] Connection closed. Code: ' . $code . '; Reason: ' . $reason . PHP_EOL;
    });
}, function (\Exception $e) {
    echo "Could not connect: {$e->getMessage()}" . PHP_EOL;
});