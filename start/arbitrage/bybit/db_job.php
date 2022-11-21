<?php

use Src\Databases\MySql;
use Src\Support\Config;
use Src\Support\Log;
use Src\Support\Time;

require_once dirname(__DIR__, 3) . '/index.php';

$redis = \Src\Databases\Redis::init();
$db = MySql::init(Config::file('db', 'mysql'));

while (true) {
    usleep(1000);

    if ($balances = $redis->get('balances')) {
        try {
            $db->replaceBalances($balances['exchange'], $balances['asset'], $balances['balance'])->execute();

            $balances = $db->query(/** @lang sql */ 'SELECT asset, SUM(total) total FROM balances GROUP BY asset')->keyPair();
            $max_group_id = $db->select('balances_history', ['group_id'])->orderByDesc('group_id')->limit(1)->get();
            foreach ($balances as $asset => $balance)
                $db->insert('balances_history', ['group_id' => (!empty($max_group_id['group_id'])) ? $max_group_id['group_id'] + 1 : 1, 'asset' => $asset, 'balance' => round($balance, 8)])->execute();
        } catch (Exception $e) {
            Log::error($e, ['$balances' => $balances]);
        }
        echo '[' . date('Y-m-d H:i:s') . '] [INFO] Balance update: ' . $balances['exchange'] . ', ' . $balances['asset'] . ', free: ' . $balances['balance']['free'] . ', used: ' . $balances['balance']['used'] . ', total: ' . $balances['balance']['total'] . PHP_EOL;
    }

    if ($order = $redis->get('orders')) {
        try {
            $db->insertOrUpdateOrders(
                $order['exchange'],
                $order['id'],
                $order['symbol'],
                $order['side'],
                $order['price'],
                $order['amount'],
                $order['quote'],
                $order['status'],
                $order['filled'],
                $order['timestamp'],
                $order['datetime'],
            )->execute();
        } catch (Exception $e) {
            Log::error($e, ['$order' => $order]);
        }
        echo '[' . date('Y-m-d H:i:s') . '] [INFO] Order update: ' . $order['exchange'] . ', ' . $order['id'] . ', ' . $order['symbol'] . ', ' . $order['side'] . ', ' . $order['price'] . ', ' . $order['amount'] . ', ' . $order['status'] . PHP_EOL;
    }

    if ($mirror_order_and_trade = $redis->get('mirror_order_and_trade')) {
        [$trade, $order] = [$mirror_order_and_trade['trade'], $mirror_order_and_trade['order']];

        try {
            $db->insertTrades(
                $trade['exchange'],
                $trade['trade_id'],
                $trade['order_id'],
                $trade['symbol'],
                $trade['trade_type'],
                $trade['side'],
                $trade['price'],
                $trade['amount'],
                $trade['quote'],
                $trade['fee']['asset'] ?? '',
                $trade['fee']['amount'] ?? 0,
                $trade['timestamp'],
                $trade['datetime']
            )->execute();
        } catch (Exception $e) {
            Log::error($e, ['$trade' => $trade]);
        }

        if ($order) {
            $trade_last_id = $db->getLastInsertId()->get()['last_id'];

            try {
                $db->insertOrUpdateOrders(
                    $order['exchange'],
                    $order['id'],
                    $order['symbol'],
                    $order['side'],
                    $order['price'],
                    $order['amount'],
                    $order['quote'],
                    $order['status'],
                    $order['filled'],
                    $order['timestamp'],
                    $order['datetime']
                )->execute();
            } catch (Exception $e) {
                Log::error($e, ['$order' => $order]);
            }

            $order_last_id = $db->getLastInsertId()->get()['last_id'];

            $db->insertMirrorTrades($trade_last_id, $order_last_id)->execute();

            echo '[' . date('Y-m-d H:i:s') . '] [INFO] Trade was: ' . $trade['symbol'] . ', ' . $trade['trade_id'] . ', ' . $trade['order_id'] . ', ' . $trade['side'] . ', ' . $trade['price'] . ', ' . $trade['amount'] . ', ' . $trade['quote'] . ', ' . $trade['datetime'] . PHP_EOL;
            echo '[' . date('Y-m-d H:i:s') . '] [INFO] Order update: ' . $order['symbol'] . ', ' . $order['id'] . ', ' . $order['side'] . ', ' . $order['price'] . ', ' . $order['amount'] . ', ' . $order['quote'] . ', ' . $order['datetime'] . PHP_EOL;
        } else {
            echo '[' . date('Y-m-d H:i:s') . '] [INFO] Less amount: ' . $trade['symbol'] . ', ' . $trade['trade_id'] . ', ' . $trade['order_id'] . ', ' . $trade['side'] . ', ' . $trade['price'] . ', ' . $trade['amount'] . ', ' . $trade['quote'] . ', ' . $trade['datetime'] . PHP_EOL;
        }
    }

    if (Time::up(60, 'db_job_len', true))
        echo '[' . date('Y-m-d H:i:s') . '] [INFO] balances: ' . $redis->getLen('balances') . ', orders: ' . $redis->getLen('orders') . ', mirror_order_and_trade: ' . $redis->getLen('mirror_order_and_trade') . PHP_EOL;
}

/*
    $balances = [
        'exchange' => 'exmo',
        'asset' => 'USDT',
        'balance' => [
            'free' => 1,
            'used' => 1,
            'total' => 2
        ]
    ];

    $order = [
        'exchange' => 'exmo',
        'id' => 1234,
        'symbol' => 'BTC/USDT',
        'side' => 'sell',
        'price' => 20000,
        'amount' => 1.1,
        'quote' => 22000,
        'status' => 'canceled',
        'filled' => 0,
        'timestamp' => 1668682485,
        'datetime' => '2022--11-17 10:54:45'
    ];

    $mirror_order_and_trade = [
        'trade' => [
            'exchange' => 'exmo',
            'trade_id' => 1234,
            'order_id' => 10000,
            'symbol' => 'BTC/USDT',
            'trade_type' => 'maker',
            'side' => 'sell',
            'price' => 22000,
            'amount' => 1,
            'quote' => 22000,
            'timestamp' => 1668682485,
            'datetime' => '2022--11-17 10:54:45',
            'fee' => [
                'amount' => 1,
                'asset' => 'USDT'
            ]
        ],
        'order' => [
            'exchange' => 'binance',
            'id' => 1234,
            'symbol' => 'BTC/USDT',
            'side' => 'sell',
            'price' => 20000,
            'amount' => 1.1,
            'quote' => 22000,
            'status' => 'canceled',
            'filled' => 0,
            'timestamp' => 1668682485,
            'datetime' => '2022--11-17 10:54:45'
        ]
    ];
*/
