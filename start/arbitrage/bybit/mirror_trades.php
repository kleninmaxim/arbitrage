<?php

use Src\Crypto\Ccxt;
use Src\Crypto\Exchanges\Original\Bybit\Spot;
use Src\Support\Config;
use Src\Support\Log;
use Src\Support\Math;
use function Ratchet\Client\connect;

require_once dirname(__DIR__, 3) . '/index.php';
require_once __DIR__ . '/helper/bybit_functions.php';

connect('wss://stream.bybit.com/spot/private/v3')->then(function ($conn) {
    // CONFIG
    $config = Config::config('arbitrage', 'bybit');

    $exchange = $config['exchange'];
    $market_discovery = $config['market_discovery'];
    $min_deal_amount = $config['min_deal_amount'];
    $assets = $config['assets'];
    $use_markets = $config['use_markets'];
    $info_of_markets = $config['info_of_markets'];
    // CONFIG

    // API KEYS
    $api_keys_exchange = Config::file('keys', $exchange);
    $api_keys_market_discovery = Config::config('keys', $market_discovery, 'main');
    // API KEYS

    // CCXT
    $ccxt_exchange = Spot::init($api_keys_exchange['api_public'], $api_keys_exchange['api_private'], ['symbols' => $use_markets]);
    $ccxt_market_discovery = Ccxt::init($market_discovery, api_key: $api_keys_market_discovery['api_public'], api_secret: $api_keys_market_discovery['api_private']);
    // CCXT

    // COUNT NECESSARY INFO
    $memcached = \Src\Databases\Memcached::init();
    $redis = \Src\Databases\Redis::init();
    $markets = $ccxt_exchange->getMarketsWithOrigin();
    // COUNT NECESSARY INFO

    // LOGIN AND SUBSCRIBE
    $expires = (time() + 3) * 1000;
    $conn->send(json_encode([
        'op' => 'auth',
        'args' => [$api_keys_exchange['api_public'], $expires, generateSignature($api_keys_exchange['api_private'], $expires)]
    ]));
    $conn->send(json_encode([
        'req_id' => 1,
        'op' => 'subscribe',
        'args' => ['ticketInfo']
    ]));
    // LOGIN AND SUBSCRIBE

    $conn->on('message', function ($msg) use (&$conn, $memcached, $redis, $ccxt_market_discovery, $exchange, $min_deal_amount, $markets, $info_of_markets) {
        if ($msg !== null) {
            $jsn_data = json_decode($msg, true);

            if (!isset($jsn_data['op']) || $jsn_data['op'] != 'pong') {
                $data = processWebsocketData(json_decode($msg, true), ['markets' => $markets]);

                if ($data['response'] == 'isUpdateSpotUserTrades') {
                    // TRADES RESULT
                    $symbol = $data['data']['symbol'];
                    $type = 'market';
                    $side = ($data['data']['side'] == 'sell') ? 'buy' : 'sell';
                    $price = $data['data']['price'];
                    // TRADES RESULT

                    // PRE COUNT
                    // FORM MEMCACHED MIRROR TRADE INFO
                    $memcached_key = 'mirrorTrades_' . $exchange;
                    $memcached_mirror_trades_info = $memcached->get($memcached_key) ?: ['data' => ['leftovers' => []], 'timestamp' => microtime(true)];
                    if (!empty($memcached_mirror_trades_info['timestamp']) && (microtime(true) - $memcached_mirror_trades_info['timestamp'] > 86400))
                        unset($memcached_mirror_trades_info);

                    if (empty($memcached_mirror_trades_info['data']['leftovers'][$symbol]))
                        $memcached_mirror_trades_info['data']['leftovers'][$symbol] = ['sell' => 0, 'buy' => 0];
                    // FORM MEMCACHED MIRROR TRADE INFO

                    $amount = Math::incrementNumber(
                        $data['data']['amount'] + $memcached_mirror_trades_info['data']['leftovers'][$symbol][$side],
                        $info_of_markets[$symbol]['amount_increment']
                    );
                    // PRE COUNT

                    echo '[' . date('Y-m-d H:i:s') . '] [START] --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------' . PHP_EOL;
                    // DECISION BY CREATE ORDER
                    $data['data']['exchange'] = $exchange;

                    if ($amount * $price > $min_deal_amount) {
                        if ($order = $ccxt_market_discovery->createOrder($symbol, $type, $side, $amount)) {
                            $memcached_mirror_trades_info['data']['leftovers'][$symbol][$side] = 0;
                            echo '[' . date('Y-m-d H:i:s') . '] [INFO] Create mirror order: ' . $order['symbol'] . ', ' . $order['type'] . ', ' . $order['side'] . ', ' . $order['price'] . ', ' . $order['amount'] . PHP_EOL;
                            $redis->queue('mirror_order_and_trade', [
                                'trade' => $data['data'],
                                'order' => [
                                    'exchange' => $ccxt_market_discovery->name,
                                    'id' => $order['id'],
                                    'symbol' => $order['symbol'],
                                    'side' => $order['side'],
                                    'price' => $order['price'],
                                    'amount' => $order['amount'],
                                    'quote' => $order['cost'],
                                    'status' => $order['status'],
                                    'filled' => $order['filled'],
                                    'timestamp' => $order['timestamp'] / 1000,
                                    'datetime' => date('Y-m-d H:i:s', round($order['timestamp'] / 1000))
                                ]
                            ]);
                        } else {
                            $memcached_mirror_trades_info['data']['leftovers'][$symbol][$side] += $data['data']['amount'];
                            echo '[' . date('Y-m-d H:i:s') . '] [WARNING] Mirror trade is empty' . PHP_EOL;
                            Log::warning(['$order' => $order, 'message' => 'Mirror trade is null', 'file' => __FILE__]);
                            $redis->queue('mirror_order_and_trade', ['trade' => $data['data'], 'order' => []]);
                        }
                    } else {
                        $memcached_mirror_trades_info['data']['leftovers'][$symbol][$side] += $data['data']['amount'];
                        echo '[' . date('Y-m-d H:i:s') . '] [INFO] Less amount: ' . $symbol . ', ' . $type . ', ' . $side . PHP_EOL;
                        $redis->queue('mirror_order_and_trade', ['trade' => $data['data'], 'order' => []]);
                    }
                    // DECISION BY CREATE ORDER

                    // END COUNTING
                    $memcached->set($memcached_key, $memcached_mirror_trades_info['data']);
                    echo '[' . date('Y-m-d H:i:s') . '] [INFO] Trade was: ' . $symbol . ', ' . $data['data']['trade_type'] . ', ' . $data['data']['side'] . ', ' . $price . ', ' . $data['data']['amount'] . PHP_EOL;
                    echo '[' . date('Y-m-d H:i:s') . '] [END] --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------' . PHP_EOL;
                    // END COUNTING
                } elseif ($data['response'] == 'isLoggedIn') {
                    echo '[' . date('Y-m-d H:i:s') . '] [INFO] Successful logged in ' . $data['data']['conn_id']. PHP_EOL;
                } elseif ($data['response'] == 'isSubscribed') {
                    echo '[' . date('Y-m-d H:i:s') . '] [INFO] Conn: ' . $data['data']['conn_id'] . ' is ' . $data['data']['op'] . PHP_EOL;
                } else {
                    Log::warning(['message' => 'Unexpected data get from websocket', 'file' => __FILE__, '$data' => $data]);
                }
            }

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
    });
}, function (Exception $e) {
    echo "Could not connect: {$e->getMessage()}" . PHP_EOL;
});