<?php

use Src\Crypto\Watchers\BinanceWatcher;
use Src\Services\Orderbook\Orderbook;

require_once dirname(__DIR__, 2) . '/index.php';

try {
    $orderbook = Orderbook::init(
        BinanceWatcher::init(['BTC/USDT', 'ETH/USDT'])
    );

    $orderbook->watchOrderbook(BinanceWatcher::WEBSOCKET);
} catch (Exception $e) {
    echo '[' . date('Y-m-d H:i:s') . '] It is Error: ' . $e->getMessage() . PHP_EOL;
}