<?php

use Src\Algo\Arbitrage;
use Src\Crypto\Ccxt;
use Src\Services\Orderbook\OrderbookWorker;
use Src\Support\Config;
use Src\Support\Filter;
use Src\Support\Log;
use Src\Support\Time;

require_once dirname(__DIR__) . '/index.php';

$config = Config::config('arbitrage', 'first');

$exchange = $config['exchange'];
$market_discovery = $config['market_discovery'];
$fees = $config['fees'];
$quote_asset = $config['quote_asset'];
$min_deal_amount = $config['min_deal_amount'];
$profits = $config['profits'];
$lifetime = $config['lifetime'];
$assets = $config['assets'];
$use_markets = $config['use_markets'];
$markets = $config['markets'];

$orderbook_worker = OrderbookWorker::init();

$arbitrage = new Arbitrage(
    Ccxt::init($exchange),
    Ccxt::init($market_discovery),
    $assets
);

while (true) {
    sleep(1);

    if (
        $data = Filter::memcachedDataOrderbookByTimestamp(
            $orderbook_worker->getOrderbook(['BTC/USDT', 'ETH/USDT'], ['binance', 'exmo']),
            $lifetime
        )
    ) {
        $orderbooks = $arbitrage->formatOrderbook($data);

        if ($arbitrage->proofOrderbooks($orderbooks, $use_markets)) {
            $prices = $arbitrage->getPrices($orderbooks, $market_discovery);

            $arbitrage->checkOpenOrdersAndCreateMarketOrders($orderbooks, $prices, $min_deal_amount, $markets);

            $arbitrage->updatePositions($prices, $exchange, $market_discovery, $quote_asset, $use_markets);

            $arbitrage->createLimitOrders($orderbooks, $use_markets, $min_deal_amount, $profits, $fees, $markets);

            $arbitrage->update($assets);

            if (!$arbitrage->proofOpenOrdersAndMirrorOrders() && Time::up(5, 'proofOpenOrdersAndMirrorOrders', true))
                Log::warning(['message' => 'proofOpenOrdersAndMirrorOrders is false', '$arbitrage' => $arbitrage]);

            if (!$arbitrage->proofBalances() && Time::up(5, 'proofOpenOrdersAndMirrorOrders', true))
                Log::warning(['message' => 'proofBalances is false', '$arbitrage' => $arbitrage]);
        } elseif (Time::up(5, 'not_proof_orderbooks', true))
            Log::warning(['message' => 'Not proof orderbooks', '$orderbooks' => $orderbooks]);
    } elseif (Time::up(5, 'empty_data', true))
        Log::warning('Data Getting From Memcached Not Filtered By Timestamp');
}
