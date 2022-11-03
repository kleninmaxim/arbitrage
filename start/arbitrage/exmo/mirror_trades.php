<?php

use Src\Crypto\Ccxt;
use Src\Support\Config;
use Src\Support\Log;
use Src\Support\Math;
use function Ratchet\Client\connect;

require_once dirname(__DIR__, 3) . '/index.php';

connect('wss://ws-api.exmo.com:443/v1/private')->then(function ($conn) {
    // CONFIG
    $config = Config::config('arbitrage', 'first');

    $exchange = $config['exchange'];
    $market_discovery = $config['market_discovery'];
    $min_deal_amount = $config['min_deal_amount'];
    $assets = $config['assets'];
    $use_markets = $config['use_markets'];
    $info_of_markets = $config['info_of_markets'];
    // CONFIG

    // API KEYS
    $api_keys_exchange = Config::config('keys', $exchange, 'mirror_trades_key');
    $api_keys_market_discovery = Config::config('keys', $market_discovery, 'main');
    // API KEYS

    // CCXT
    $ccxt_exchange = Ccxt::init($exchange, api_key: $api_keys_exchange['api_public'], api_secret: $api_keys_exchange['api_private']);
    $ccxt_market_discovery = Ccxt::init($market_discovery, api_key: $api_keys_market_discovery['api_public'], api_secret: $api_keys_market_discovery['api_private']);
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
        'topics' => ['spot/user_trades']
    ]));
    // LOGIN AND SUBSCRIBE

    $conn->on('message', function ($msg) use ($memcached, $ccxt_market_discovery, $exchange, $min_deal_amount, $markets, $info_of_markets) {
        if ($msg !== null) {
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
                $memcached_mirror_trades_info = $memcached->get($memcached_key);
                if (microtime(true) - $memcached_mirror_trades_info['timestamp'] < 86400)
                    unset($memcached_mirror_trades_info);

                if (empty($memcached_mirror_trades_info['data']['leftovers'][$symbol]))
                    $memcached_mirror_trades_info['leftovers'][$symbol] = ['sell' => 0, 'buy' => 0];
                // FORM MEMCACHED MIRROR TRADE INFO

                $amount = Math::incrementNumber(
                    $data['data']['amount'] + $memcached_mirror_trades_info['leftovers'][$symbol][$side],
                    $info_of_markets[$symbol]['amount_increment']
                );
                // PRE COUNT

                // DECISION BY CREATE ORDER
                if ($amount * $price > $min_deal_amount) {
                    $ccxt_market_discovery->createOrder($symbol, $type, $side, $amount);

                    $memcached_mirror_trades_info['leftovers'][$symbol][$side] = 0;
                    echo '[' . date('Y-m-d H:i:s') . '] [INFO] Create mirror order: ' . $symbol . ', ' . $type . ', ' . $side . ', ' . $price . ', ' . $amount . PHP_EOL;
                } else {
                    $memcached_mirror_trades_info['leftovers'][$symbol][$side] += $data['data']['amount'];
                    echo '[' . date('Y-m-d H:i:s') . '] [INFO] Less amount: ' . $symbol . ', ' . $type . ', ' . $side . ', ' . $price . ', ' . $amount . PHP_EOL;
                }
                // DECISION BY CREATE ORDER

                // END COUNTING
                $memcached->set($memcached_key, $memcached_mirror_trades_info['data']);
                echo '[' . date('Y-m-d H:i:s') . '] [INFO] Trade was: ' . $symbol . ', ' . $data['data']['type'] . ', ' . $data['data']['side'] . ', ' . $price . ', ' . $data['data']['amount'] . PHP_EOL;
                // END COUNTING
            } elseif ($data['response'] == 'isConnectionEstablished') {
                echo '[' . date('Y-m-d H:i:s') . '] [INFO] Connection is established with session id: ' . $data['data']['session_id'] . PHP_EOL;
            } elseif ($data['response'] == 'isLoggedIn') {
                echo '[' . date('Y-m-d H:i:s') . '] [INFO] Successful logged in.' . PHP_EOL;
            } elseif ($data['response'] == 'isSubscribed') {
                echo '[' . date('Y-m-d H:i:s') . '] [INFO] Topic: ' . $data['data']['topic'] . ' is ' . $data['data']['event'] . PHP_EOL;
            } else {
                Log::warning(['message' => 'Unexpected data get from websocket', '$data' => $data]);
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

/**
 * @throws Exception
 */
function processWebsocketData(mixed $data, array $options): array
{
    if ($is = isConnectionEstablished($data))
        return $is;

    if ($is = isLoggedIn($data))
        return $is;

    if ($is = isSubscribed($data))
        return $is;

    if ($is = isUpdateSpotUserTrades($data, $options['markets']))
        return $is;

    return ['response' => 'error', 'data' => null];
}

function isUpdateSpotUserTrades($data, $markets): array
{
    if (
        !empty($data['ts']) &&
        !empty($data['event']) &&
        !empty($data['topic']) &&
        !empty($data['data']) &&
        $data['event'] == 'update' &&
        $data['topic'] == 'spot/user_trades'
    ) {
        $timestamp_in_seconds = floor($data['ts'] / 1000);

        return [
            'response' => 'isUpdateSpotUserTrades',
            'data' => [
                'symbol' => $markets['origin'][$data['data']['pair']],
                'trade_id' => $data['data']['trade_id'],
                'order_id' => $data['data']['order_id'],
                'type' => $data['data']['type'],
                'price' => $data['data']['price'],
                'amount' => $data['data']['quantity'],
                'quote' => $data['data']['amount'],
                'timestamp' => $timestamp_in_seconds,
                'trade_type' => $data['data']['exec_type'],
                'datetime' => date('Y--m-d H:i:s', $timestamp_in_seconds),
                'fee' => [
                    'amount' => $data['data']['commission_amount'],
                    'asset' => $data['data']['commission_currency']
                ]
            ]
        ];
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

function isLoggedIn($data): array
{
    if (
        !empty($data['ts']) &&
        !empty($data['event']) &&
        !empty($data['id']) &&
        $data['event'] == 'logged_in'
    ) {
        $data['datetime'] = date('Y-m-d H:i:s', floor($data['ts'] / 1000));

        return ['response' => 'isLoggedIn', 'data' => $data];
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

function originCcxtMarketIds(array $fetch_markets, array $use_markets): array
{
    foreach (
        array_filter(
            $fetch_markets,
            fn($market) => in_array($market, $use_markets),
            ARRAY_FILTER_USE_KEY
        ) as $symbol => $market
    ) {
        $markets['origin'][$market['id']] = $symbol;
        $markets['ccxt'][$symbol] = $market;
    }
    return $markets ?? [];
}

function generateSignature(string $api_public, string $api_private, string $nonce): string
{
    return base64_encode(hash_hmac('sha512', $api_public . $nonce, $api_private, true));
}