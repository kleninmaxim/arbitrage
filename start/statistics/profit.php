<?php

use Src\Databases\MySql;
use Src\Support\Config;
use Src\Support\Math;

require_once dirname(__DIR__, 2) . '/index.php';

if (isset($argv[1]))
    $echo_full = (bool) $argv[1];

$db = MySql::init(Config::config('db', 'mysql', 'localhost'));

$data = $db->query(/** @lang sql */ '
    SELECT 
        m.trade_id trade_id,
        m.order_id order_id,
        t.symbol symbol,
        t.exchange_id trade_exchange_id,
        o.exchange_id order_exchange_id,
        t.side trade_side,
        o.side order_side,
        t.price trade_price,
        o.price order_price,
        t.amount trade_amount,
        o.amount order_amount,
        t.quote trade_quote,
        o.quote order_quote,
        t.timestamp trade_timestamp,
        o.timestamp order_timestamp,
        t.datetime trade_datetime,
        o.datetime order_datetime
    FROM mirror_trades m 
        LEFT JOIN trades t ON t.id= m.trade_id
        LEFT JOIN orders o ON o.id= m.order_id
')->getAll();

$mirror_trades = [];
foreach ($data as $datum) {
    $mirror_trades[] = [
        'symbol' => $datum['symbol'],
        'trade_id' => $datum['trade_id'],
        'order_id' => $datum['order_id'],
        $datum['trade_side'] => [
            'exchange' => $datum['trade_exchange_id'],
            'price' => $datum['trade_price'],
            'amount' => $datum['trade_amount'],
            'quote' => $datum['trade_quote'],
            'timestamp' => $datum['trade_timestamp'],
            'datetime' => $datum['trade_datetime'],
        ],
        $datum['order_side'] => [
            'exchange' => $datum['order_exchange_id'],
            'price' => $datum['order_price'],
            'amount' => $datum['order_amount'],
            'quote' => $datum['order_quote'],
            'timestamp' => $datum['order_timestamp'],
            'datetime' => $datum['order_datetime'],
        ]
    ];
}

$sum_price = 0;
foreach ($mirror_trades as $mirror_trade) {
    $mes = '';
    if (Math::compareFloats($mirror_trade['sell']['amount'], $mirror_trade['buy']['amount']))
        $mes = ' Quote: ' . round($mirror_trade['sell']['quote'] - $mirror_trade['buy']['quote'], 8);
    $price = round($mirror_trade['sell']['price'] - $mirror_trade['buy']['price'], 8);

    if (isset($echo_full) && $echo_full) {
        echo '[' . date('Y-m-d H:i:s') . '] Price: ' . $price . ' Time: ' . abs(round($mirror_trade['sell']['timestamp'] - $mirror_trade['buy']['timestamp'], 4)) . $mes . PHP_EOL;
        if ($price < 0)
            echo '[' . date('Y-m-d H:i:s') . '] [WARNING] ' . print_r($mirror_trade, true) . PHP_EOL;
    }

    $sum_price += $price;
}

echo '[' . date('Y-m-d H:i:s') . '] SUM: ' . $sum_price . PHP_EOL;

$min_and_max = $db->query(/** @lang sql */ '
    SELECT MIN(group_id) min, MAX(group_id) max FROM balances_history;
')->get();

[$min, $max] = [$min_and_max['min'], $min_and_max['max']];

if ($min && $max) {
    $start_balance_history = $db->select('balances_history', ['asset', 'balance'])->where(['group_id', '=', $min])->keyPair();
    $end_balance_history = $db->select('balances_history', ['asset', 'balance'])->where(['group_id', '=', $max])->keyPair();

    $msg = '';
    foreach ($end_balance_history as $asset => $amount) {
        if (!empty($start_balance_history[$asset])) {
            $msg .= $asset . ': ' . rtrim(sprintf("%.8f", round($amount - $start_balance_history[$asset], 8)), '0') . ' ';
        } else {
            echo '[' . date('Y-m-d H:i:s') . '] [WARNING] Empty: ' . $asset . PHP_EOL;
        }
    }

    echo '[' . date('Y-m-d H:i:s') . '] Real profit: ' . $msg . PHP_EOL;
} else {
    echo '[' . date('Y-m-d H:i:s') . '] Real profit: 0' . PHP_EOL;
}