<?php

use ccxt\Exchange;

require_once dirname(__DIR__) . '/index.php';

$exchanges = Exchange::$exchanges;

$diff = ['eqonex', 'lbank', 'woo', 'bitstamp1', 'bl3p', 'coinspot', 'paymium', 'tidebit', 'coinbase'];

foreach (array_diff($exchanges, $diff) as $exchange) {
    $exchange_class = '\\ccxt\\' . $exchange;

    $ccxt = new $exchange_class();

    echo '[' . date('Y-m-d H:i:s') . '] Exchange: ' . $exchange . PHP_EOL .
        '                                       has fetchOrderBook: ' . ($ccxt->has['fetchOrderBook'] ? 'true' : 'false') . PHP_EOL .
        '                                       has createOrder: ' . ($ccxt->has['createOrder'] ? 'true' : 'false') . PHP_EOL .
        '                                       has cancelOrder: ' . ($ccxt->has['cancelOrder'] ? 'true' : 'false') . PHP_EOL .
        '                                       has fetchBalance: ' . ($ccxt->has['fetchBalance'] ? 'true' : 'false') . PHP_EOL .
        '                                       has fetchMyTrades: ' . ($ccxt->has['fetchMyTrades'] ? 'true' : 'false') . PHP_EOL .
        '                                       has fetchOpenOrders: ' . ($ccxt->has['fetchOpenOrders'] ? 'true' : 'false') . PHP_EOL .
        '                                       has fetchOrder: ' . ($ccxt->has['fetchOrder'] ? 'true' : 'false') . PHP_EOL .
        '                                       has fetchOrders: ' . ($ccxt->has['fetchOrders'] ? 'true' : 'false') .
        PHP_EOL;

    if (!$ccxt->has['fetchOrderBook'])
        $no_fetchOrderBook[] = $exchange;

    if (!$ccxt->has['createOrder'])
        $no_createOrder[] = $exchange;

    if (!$ccxt->has['cancelOrder'])
        $no_cancelOrder[] = $exchange;

    if (!$ccxt->has['fetchBalance'])
        $no_fetchBalance[] = $exchange;

    if (!$ccxt->has['fetchBalance'])
        $no_fetchMyTrades[] = $exchange;

    if (!$ccxt->has['fetchOpenOrders'])
        $no_fetchOpenOrders[] = $exchange;

    if (!$ccxt->has['fetchOrder'])
        $no_fetchOrder[] = $exchange;

    if (!$ccxt->has['fetchOrders'])
        $no_fetchOrders[] = $exchange;
}

echo '[fetchOrderBook] ' . implode(', ', $no_fetchOrderBook ?? []) . PHP_EOL;
echo '[createOrder] ' . implode(', ', $no_createOrder ?? []) . PHP_EOL;
echo '[cancelOrder] ' . implode(', ', $no_cancelOrder ?? []) . PHP_EOL;
echo '[fetchBalance] ' . implode(', ', $no_fetchBalance ?? []) . PHP_EOL;
echo '[fetchMyTrades] ' . implode(', ', $no_fetchMyTrades ?? []) . PHP_EOL;
echo '[fetchOpenOrders] ' . implode(', ', $no_fetchOpenOrders ?? []) . PHP_EOL;
echo '[fetchOrder] ' . implode(', ', $no_fetchOrder ?? []) . PHP_EOL;
echo '[fetchOrders] ' . implode(', ', $no_fetchOrders ?? []) . PHP_EOL;