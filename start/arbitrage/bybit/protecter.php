<?php

use Src\Support\Log;
use Src\Support\Pm2;

require_once dirname(__DIR__, 3) . '/index.php';

$memcached = \Src\Databases\Memcached::init();

echo '[' . date('Y-m-d H:i:s') . '] ' .  ($memcached->flush() ? 'Successful flush' : '[WARNING] NOT FLUSH') . PHP_EOL;

$memcached->set('is_good_arbitrage', true);

$name = 'ACCOUNT BYBIT';

while (true) {
    usleep(200000);

    $data = $memcached->get('is_good_arbitrage');

    if (isset($data['data'])) {
        if (!$data['data']) {
            Pm2::stopAll();

//            Pm2::stopByName($name);
//            $processes = array_filter(Pm2::list(), fn($process) => $process['name'] == $name);
//            if (!empty($processes) && count($processes) == 1) {
//                $process = array_shift($processes);
//
//                sleep(10);
//            } else {
//                Log::warning(['file' => __FILE__, 'message' => 'Incorrect Pm2 Processes', '$processes' => $processes]);
//                echo '[' . date('Y-m-d H:i:s') . '] [WARNING] Incorrect Pm2 Processes' . PHP_EOL;
//            }
        }
    } else {
        Log::warning(['file' => __FILE__, 'message' => 'Empty data', '$data' => $data]);
        echo '[' . date('Y-m-d H:i:s') . '] [WARNING] $data[\'data\'] is empty!!!' . PHP_EOL;
    }
}