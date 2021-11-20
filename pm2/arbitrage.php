<?php

require_once dirname(__DIR__) . '/helper/bootstrap.php';

//buy = asks, sell = bids

$pairs = [
    'ETH/BUSD',
    'ETH/BTC',
    'BTC/BUSD'
];

$makers = [
    [$pairs[0] => 'buy'],
    [$pairs[0] => 'sell'],
];

$seconds = 2;

while (true) {

    sleep($seconds);

    $run = \app\Arbitrage::run(
        $pairs,
        $makers
    );

    if (is_array($run)) {

        foreach ($run as $item) echo 'Profit percentage arbitrage is: ' . $item . PHP_EOL;

    } else echo 'Something wrong' . PHP_EOL;

}
