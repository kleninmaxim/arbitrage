<?php

use Src\Services\Orderbook\Orderbook;

require_once dirname(__DIR__, 2) . '/index.php';

$orderbook = Orderbook::init();

while (true) {
    sleep(1);

    if ($ors = $orderbook->getOrderbook(['BTC/USDT', 'ETH/USDT'], 'binance')) {
        foreach ($ors as $or) {
            $data = $or['data'];
            echo '[' . date('Y-m-d H:i:s') . '] ' . $data['asks'][0][0] . ' ' . $data['bids'][0][0] . PHP_EOL;
        }
    } else {
        echo '[' . date('Y-m-d H:i:s') . '] WARRING: No Orderbooks' . PHP_EOL;
    }
}