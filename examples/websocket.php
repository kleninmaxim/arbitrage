<?php

use Src\Support\Websocket;

require_once dirname(__DIR__) . '/index.php';

$websocket = Websocket::init('wss://ws.okx.com:8443/ws/v5/public', ['timeout' => 1000]);

$websocket->send([
    'op' => 'subscribe',
    'args' => [[
        'channel' => 'candle1m',
        'instId' => 'BTC-USDT'
    ]]
]);

while (true) {
    $data = $websocket->receive();

    if (!empty($data['data'])) {
        echo '[' . date('Y-m-d H:i:s') . '] Open: ' . $data['data'][0][1] . ' High: ' . $data['data'][0][2] . ' Low: ' . $data['data'][0][3] . ' Close: ' . $data['data'][0][4] . '. Time: ' . microtime(true) . PHP_EOL;
    } else {
        echo '[' . date('Y-m-d H:i:s') . '] Empty' . PHP_EOL;
    }
}
