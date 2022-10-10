<?php

use Src\Crypto\Watchers\CcxtWatcher;
use Src\Services\Orderbook\Orderbook;
use Src\Support\Config;
use Src\Support\Log;

require_once dirname(__DIR__, 3) . '/index.php';

if (!isset($argv[1]))
    die('Set key parameters');

$key = $argv[1];

$config = Config::config('services_orderbooks', 'watchers', 'ccxt', $key);

$exchange = $config['exchange'];
$symbol = $config['symbol'];
$service_name = $config['name'];

$orderbook = Orderbook::init(CcxtWatcher::init($exchange, $service_name, $symbol));

try {
    $orderbook->watchOrderbook(CcxtWatcher::REST);
} catch (Exception $e) {
    Log::error($e, $config);
}