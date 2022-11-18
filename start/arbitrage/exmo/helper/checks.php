<?php

require_once dirname(__DIR__, 4) . '/index.php';

$memcached = new Memcached();
if ($memcached->addServer('127.0.0.1', 11211)) {
    echo '[' . date('Y-m-d H:i:s') . '] [OK] Memcached' . PHP_EOL;
} else {
    echo '[' . date('Y-m-d H:i:s') . '] [FAIL] Memcached' . PHP_EOL;
}

$redis = new Redis();
if ($redis->connect('127.0.0.1')) {
    echo '[' . date('Y-m-d H:i:s') . '] [OK] Redis' . PHP_EOL;
} else {
    echo '[' . date('Y-m-d H:i:s') . '] [FAIL] Redis' . PHP_EOL;
}
