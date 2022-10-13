<?php

use Src\Support\Config;
use Src\Support\Pm2;

require_once dirname(__DIR__, 3) . '/index.php';

$watchers = Config::config('services_orderbooks', 'watchers', 'dev');

foreach ($watchers as $service_name => $settings)
    foreach ($settings as $key => $setting) {
        $is_start = Pm2::start(
            __DIR__ . '/' . $service_name . '.php',
            $setting['name'],
            'orderbooks',
            [$key],
            true
        );

        if ($is_start) {
            echo '[' . date('Y-m-d H:i:s') . '] [OK] ' . $setting['name'] . PHP_EOL;
        } else {
            echo '[' . date('Y-m-d H:i:s') . '] [ERROR] ' . $setting['name'] . PHP_EOL;
        }
    }
