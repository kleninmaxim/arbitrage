<?php

require_once __DIR__ . '/helper/bootstrap.php';

\src\Cache::createFiles();

$pairs = \src\Ccxt::getMarkets();

\src\Cache::putPairs($pairs);

$triangles = \app\Start::getTriangles($pairs);

$pairs = \app\Start::getPairsByTriangles($triangles);

\src\Cache::putTriangles($triangles);

\src\DB::createTables();

foreach ($pairs as $pair)
    \app\Start::pmStartOrderBook($pair);

foreach ($triangles as $triangle)
    \app\Start::pmStartTriangles($triangle);

echo 'Done' . PHP_EOL;
