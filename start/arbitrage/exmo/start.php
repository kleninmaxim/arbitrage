<?php

use Src\Crypto\Ccxt;
use Src\Support\Config;

require_once dirname(__DIR__, 3) . '/index.php';

$config = Config::config('arbitrage', 'first');
$exchange = $config['exchange'];

$api_keys_exchange = Config::file('keys', $exchange);

$ccxt_exchange = Ccxt::init($exchange, api_key: $api_keys_exchange['api_public'], api_secret: $api_keys_exchange['api_private']);

$ccxt_exchange->cancelAllOrder();

// Clean Memcached