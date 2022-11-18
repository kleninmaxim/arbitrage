<?php

require_once dirname(__DIR__, 4) . '/index.php';

$memcached = new Memcached();
$memcached->addServer('127.0.0.1', 11211);
if ($memcached->flush()) {
    echo '[' . date('Y-m-d H:i:s') . '] Successful flush' . PHP_EOL;
} else {
    echo '[' . date('Y-m-d H:i:s') . '] [WARNING] NOT FLUSH' . PHP_EOL;
}
