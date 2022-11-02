<?php

use Src\Crypto\Ccxt;
use Src\Support\Config;

require_once dirname(__DIR__, 3) . '/index.php';

$config = Config::config('arbitrage', 'first');

$exchange = $config['exchange'];
$market_discovery = $config['market_discovery'];
$sleep = $config['sleep'];
$info_of_markets = $config['info_of_markets'];

$api_keys_exchange = Config::config('keys', $exchange, 'mirror_trades_key');
$api_keys_market_discovery = Config::config('keys', $market_discovery, 'main');

$ccxt_exchange = Ccxt::init($exchange, api_key: $api_keys_exchange['api_public'], api_secret: $api_keys_exchange['api_private']);
$ccxt_market_discovery = Ccxt::init($market_discovery, api_key: $api_keys_market_discovery['api_public'], api_secret: $api_keys_market_discovery['api_private']);


while (true) {
    sleep($sleep);

    echo '[' . date('Y-m-d H:i:s') . '] [START] --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------' . PHP_EOL;
    if ($my_trades = $ccxt_exchange->getMyTrades(['BTC/USDT', 'ETH/USDT'])) {
        print_r($my_trades); echo PHP_EOL; die();
    }
    echo '[' . date('Y-m-d H:i:s') . '] [END] --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------' . PHP_EOL;
}
