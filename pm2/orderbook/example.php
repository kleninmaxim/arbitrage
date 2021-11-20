<?php

require_once dirname(__DIR__, 2) . '/helper/bootstrap.php';

if (!isset($argv[1])) die('Give right arguments!' . PHP_EOL);

$pair = $argv[1];

\app\Websocket::connect($pair);

do {

    $run = \app\Websocket::run();

    echo 'Orderbook insert' . PHP_EOL;

} while ($run);

sleep(5);

\app\Websocket::close();
