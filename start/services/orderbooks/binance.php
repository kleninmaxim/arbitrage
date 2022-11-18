<?php

use Src\Crypto\Watchers\BinanceWatcher;
use Src\Services\Orderbook\OrderbookWorker;
use Src\Support\Config;
use Src\Support\Log;

require_once dirname(__DIR__, 3) . '/index.php';

if (!isset($argv[1]))
    die('Set key parameters');

$key = $argv[1];

$config = Config::file('services_orderbooks', 'watchers')['binance'][$key];

$markets = $config['markets'];
$service_name = $config['name'];

try {
    $orderbook = OrderbookWorker::init(BinanceWatcher::init($service_name, $markets));
    $orderbook->watchOrderbook(BinanceWatcher::WEBSOCKET);
} catch (Exception $e) {
    Log::error($e, $config);
    sleep(1);
}