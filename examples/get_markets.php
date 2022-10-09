<?php

use Src\Crypto\Ccxt;

require_once dirname(__DIR__) . '/index.php';

$ccxt = Ccxt::init('binance');

try {
    print_r($ccxt->getMarkets(['BTC', 'USDT'], false));
} catch (Exception $e) {
    echo '[' . date('Y-m-d H:i:s') . '] ' . $e->getMessage();
}