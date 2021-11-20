<?php

require_once dirname(__DIR__, 2) . '/helper/bootstrap.php';

if (!isset($argv[1]) && isset($argv[2]) && isset($argv[3])) die('Give right arguments!' . PHP_EOL);

$pairs[] = $argv[1];
$pairs[] = $argv[2];
$pairs[] = $argv[3];

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
