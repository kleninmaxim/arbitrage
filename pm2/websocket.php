<?php

require_once dirname(__DIR__) . '/helper/bootstrap.php';

\app\Websocket::connect('ETH/USDT');

do {

    $run = \app\Websocket::run();

} while ($run);

sleep(5);

\app\Websocket::close();
