<?php

require_once dirname(__DIR__, 1) . '/index.php';

$memcached = \Src\Databases\Memcached::init();

$config = [
    'debug' => true,
    'profit' => 10,
    'use_balance' => 1
];

$markets = ['BTC/USDT', 'ETH/USDT', 'ETH/BTC'];

$memcached->set(['config', 'markets'], [$config, $markets]);

print_r($memcached->get(['config', 'markets'])); echo PHP_EOL; die();

