<?php

use Src\Support\Config;
use Src\Support\Pm2;

require_once dirname(__DIR__, 3) . '/index.php';

$watchers = Config::file('services_orderbooks', 'watchers');

foreach ($watchers as $service_name => $settings) {
    $is_start = Pm2::start(
        __DIR__ . '/' . $service_name . '.php',
        $settings['name'],
        'orderbooks'
    );

    echo '[' . date('Y-m-d H:i:s') . '] ' . ($is_start ? '[OK] ' : '[ERROR] ') . $settings['name'] . PHP_EOL;
}
