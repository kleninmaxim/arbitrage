<?php

use Src\Crypto\Watchers\CcxtWatcher;
use Src\Services\Orderbook\Orderbook;

require_once dirname(__DIR__, 2) . '/index.php';

$orderbook = Orderbook::init(
    CcxtWatcher::init('binance', 'BTC/USDT')
);

try {
    $orderbook->watchOrderbook(CcxtWatcher::REST);
} catch (Exception $e) {
    echo '[' . date('Y-m-d H:i:s') . '] It is Error: ' . $e->getMessage() . PHP_EOL;
}