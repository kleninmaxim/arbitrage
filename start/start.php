<?php

use Src\Services\Orderbook\OrderbookWorker;
use Src\Support\Filter;
use Src\Support\Log;
use Src\Support\Time;

require_once dirname(__DIR__) . '/index.php';

$orderbook = OrderbookWorker::init();

$lifetime = 2;

while (true) {
    sleep(1);

    if (
        $data = Filter::memcachedDataByTimestamp(
            $orderbook->getOrderbook(['BTC/USDT', 'ETH/USDT'], ['binance', 'exmo']),
            $lifetime
        )
    ) {
        foreach ($data as $datum) {
            $or = $datum['data']['orderbook'];
            echo '[' . date('Y-m-d H:i:s') . '] ' . $or['exchange'] . ': ' . $or['symbol'] . ', ' . $or['bids'][0][0] . ', ' . $or['asks'][0][0] . PHP_EOL;
        }
    } elseif (Time::up(1, 'empty_data'))
        Log::warning('Data Getting From Memcached Not Filtered By Timestamp. ' . __FILE__);
}