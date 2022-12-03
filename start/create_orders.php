<?php

use Src\Crypto\Exchanges\Binance;
use Src\Crypto\Exchanges\Bybit;
use Src\Support\Config;
use Src\Support\Math;

require_once dirname(__DIR__) . '/index.php';

$argv[1] = 'BTC/USDT';

if (!isset($argv[1]))
    die('Set parameter: symbol');

$symbol = $argv[1];

$config = Config::config('arbitrage', 'bybit');

$sleep = $config['sleep'];
$min_deal_amount = $config['min_deal_amount'];
$max_deal_amount = $config['max_deal_amount'];
$min_profit = $config['min_profit'];
$max_get_orderbook_latency = $config['max_get_orderbook_latency'];
$fees = $config['fees'];
$assets = $config['assets'];
$info_of_markets = $config['info_of_markets'];

$price_increment = $info_of_markets[$symbol]['price_increment'];
$amount_increment = $info_of_markets[$symbol]['amount_increment'];
list($base_asset, $quote_asset) = $symbol;

$api_keys['binance'] = Config::file('keys', 'binance');
$api_keys['bybit'] = Config::file('keys', 'bybit');

$exchange_one = Binance::init($api_keys['binance']['api_public'], $api_keys['binance']['api_private']);
$exchange_two = Bybit::init($api_keys['bybit']['api_public'], $api_keys['bybit']['api_private']);

$balances_one = $exchange_one->getBalances($assets);
$balances_two = $exchange_two->getBalances($assets);

$start_balance_history = [];
foreach ($balances_one as $asset => $balance)
    $start_balance_history[$asset] = $balance + $balances_two[$asset];
$msg = '';
foreach ($start_balance_history as $asset => $amount)
    $msg .= rtrim(sprintf("%.8f", $amount), '0') . ' create_orders.php' . $asset . ', ';
echo '[' . date('Y-m-d H:i:s') . '] [INFO] Start balances: ' . preg_replace('/,([^,]*)$/', '.\1', rtrim($msg)) . PHP_EOL;

reduceBalances($balances_one);
reduceBalances($balances_two);

while (true) {
    sleep($sleep);

    $time_start = microtime(true);
    $orderbook_one = $exchange_one->getOrderbook('BTCUSDT');
    $orderbook_two = $exchange_two->getOrderbook('BTCUSDT');
    $get_orderbook_latency = microtime(true) - $time_start;

    if ($orderbook_one && $orderbook_two && ($get_orderbook_latency <= $max_get_orderbook_latency)) {
        if ($balances_one[$base_asset]['free'] > 0 && $balances_two[$quote_asset]['free'] > 0) {
            $imitation_base_asset_sell_one = imitationMarketOrderSell($orderbook_one, $balances_one[$base_asset]['free'] * 2, $price_increment);
            $imitation_quote_asset_sell_two = imitationMarketOrderBuy($orderbook_two, $balances_two[$quote_asset]['free'] * 2, $price_increment);

            if ($imitation_base_asset_sell_one['base'] < $imitation_quote_asset_sell_two['base']) {
                $imitation_quote_asset_sell_two = imitationMarketOrderBuy($orderbook_two, $imitation_base_asset_sell_one['quote'], $price_increment);
            } else {
                $imitation_base_asset_sell_one = imitationMarketOrderSell($orderbook_one, $imitation_quote_asset_sell_two['base'], $price_increment);
            }

            if (Math::compareFloats($imitation_base_asset_sell_one['base'], $imitation_quote_asset_sell_two['base'])) {
                $profit = ($imitation_base_asset_sell_one['quote'] - $imitation_quote_asset_sell_two['quote']) / ($imitation_base_asset_sell_one['quote'] + $imitation_quote_asset_sell_two['quote']) * 100;
            } else {
                $profit = ($imitation_quote_asset_sell_two['base'] - $imitation_base_asset_sell_one['base']) / ($imitation_quote_asset_sell_two['base'] + $imitation_base_asset_sell_one['base']) * 100;
            }

            if ($profit > $min_profit) {
                $imitation_base_asset_sell_for_amount_one = imitationMarketOrderSell($orderbook_one, $balances_one[$base_asset]['free'], $price_increment);
                $imitation_quote_asset_for_amount_sell_two = imitationMarketOrderBuy($orderbook_two, $balances_two[$quote_asset]['free'], $price_increment);
                if (min($imitation_base_asset_sell_for_amount_one['quote'], $imitation_quote_asset_for_amount_sell_two['quote']) > $min_deal_amount) {
                    $amount = Math::incrementNumber(min($imitation_base_asset_sell_for_amount_one['base'], $imitation_quote_asset_for_amount_sell_two['base']), $amount_increment);
                    $exchange_one->createOrder($symbol, 'MARKET', 'SELL', $amount);
                    $exchange_two->createOrder($symbol, 'MARKET', 'Buy', $amount);

                    $start_balance_history = [];
                    foreach ($balances_one as $asset => $balance)
                        $start_balance_history[$asset] = $balance + $balances_two[$asset];

                    $balances_one = $exchange_one->getBalances($assets);
                    $balances_two = $exchange_two->getBalances($assets);

                    $end_balance_history = [];
                    foreach ($balances_one as $asset => $balance)
                        $end_balance_history[$asset] = $balance + $balances_two[$asset];

                    $msg = '';
                    foreach ($end_balance_history as $asset => $amount)
                        $msg .= rtrim(sprintf("%.8f", round($amount - $start_balance_history[$asset], 8)), '0') . ' create_orders.php' . $asset . ', ';

                    echo '[' . date('Y-m-d H:i:s') . '] [INFO] Create orders. Real profit: ' . preg_replace('/,([^,]*)$/', '.\1', rtrim($msg)) . PHP_EOL;
                    echo '[' . date('Y-m-d H:i:s') . '] [INFO] Balances: ' . preg_replace('/,([^,]*)$/', '.\1', rtrim($msg)) . PHP_EOL;
                    reduceBalances($balances_one);
                    reduceBalances($balances_two);
                } else {
                    echo '[' . date('Y-m-d H:i:s') . '] [INFO] Not enough min deal amount' . PHP_EOL;
                }
            } else {
                echo '[' . date('Y-m-d H:i:s') . '] [INFO] Small profit: ' . $profit . PHP_EOL;
            }
        } else {
            echo '[' . date('Y-m-d H:i:s') . '] [INFO] Zero balance for one amount' . PHP_EOL;
        }

        $imitation_base_asset_sell_two = imitationMarketOrderSell($orderbook_two, $balances_two[$base_asset]['free'] * 2, $price_increment);
        $imitation_quote_asset_sell_one = imitationMarketOrderBuy($orderbook_one, $balances_one[$quote_asset]['free'] * 2, $price_increment);
        if ($balances_one[$quote_asset]['free'] > 0 && $balances_two[$base_asset]['free'] > 0) {
            $imitation_base_asset_sell_two = imitationMarketOrderSell($orderbook_one, $balances_one[$quote_asset]['free'] * 2, $price_increment);
            $imitation_quote_asset_sell_one = imitationMarketOrderBuy($orderbook_two, $balances_two[$base_asset]['free'] * 2, $price_increment);

            if ($imitation_base_asset_sell_two['base'] < $imitation_quote_asset_sell_one['base']) {
                $imitation_quote_asset_sell_one = imitationMarketOrderBuy($orderbook_two, $imitation_base_asset_sell_two['quote'], $price_increment);
            } else {
                $imitation_base_asset_sell_two = imitationMarketOrderSell($orderbook_one, $imitation_quote_asset_sell_one['base'], $price_increment);
            }

            if (Math::compareFloats($imitation_base_asset_sell_two['base'], $imitation_quote_asset_sell_one['base'])) {
                $profit = ($imitation_base_asset_sell_two['quote'] - $imitation_quote_asset_sell_one['quote']) / ($imitation_base_asset_sell_two['quote'] + $imitation_quote_asset_sell_one['quote']) * 100;
            } else {
                $profit = ($imitation_quote_asset_sell_one['base'] - $imitation_base_asset_sell_two['base']) / ($imitation_quote_asset_sell_one['base'] + $imitation_base_asset_sell_two['base']) * 100;
            }

            if ($profit > $min_profit) {
                $imitation_base_for_amount_sell_two = imitationMarketOrderBuy($orderbook_two, $balances_two[$base_asset]['free'], $price_increment);
                $imitation_quote_asset_sell_for_amount_one = imitationMarketOrderSell($orderbook_one, $balances_one[$quote_asset]['free'], $price_increment);
                if (min($imitation_base_for_amount_sell_two['quote'], $imitation_quote_asset_sell_for_amount_one['quote']) > $min_deal_amount) {
                    $amount = Math::incrementNumber(min($imitation_base_for_amount_sell_two['base'], $imitation_quote_asset_sell_for_amount_one['base']), $amount_increment);
                    $exchange_one->createOrder($symbol, 'MARKET', 'BUY', $amount);
                    $exchange_two->createOrder($symbol, 'MARKET', 'Sell', $amount);

                    $start_balance_history = [];
                    foreach ($balances_one as $asset => $balance)
                        $start_balance_history[$asset] = $balance + $balances_two[$asset];

                    $balances_one = $exchange_one->getBalances($assets);
                    $balances_two = $exchange_two->getBalances($assets);

                    $end_balance_history = [];
                    foreach ($balances_one as $asset => $balance)
                        $end_balance_history[$asset] = $balance + $balances_two[$asset];

                    $msg = '';
                    foreach ($end_balance_history as $asset => $amount)
                        $msg .= rtrim(sprintf("%.8f", round($amount - $start_balance_history[$asset], 8)), '0') . ' create_orders.php' . $asset . ', ';

                    echo '[' . date('Y-m-d H:i:s') . '] [INFO] Create orders. Real profit: ' . preg_replace('/,([^,]*)$/', '.\1', rtrim($msg)) . PHP_EOL;
                    echo '[' . date('Y-m-d H:i:s') . '] [INFO] Balances: ' . preg_replace('/,([^,]*)$/', '.\1', rtrim($msg)) . PHP_EOL;
                    reduceBalances($balances_one);
                    reduceBalances($balances_two);
                } else {
                    echo '[' . date('Y-m-d H:i:s') . '] [INFO] Not enough min deal amount' . PHP_EOL;
                }
            } else {
                echo '[' . date('Y-m-d H:i:s') . '] [INFO] Small profit' . PHP_EOL;
            }
        } else {
            echo '[' . date('Y-m-d H:i:s') . '] [INFO] Zero balance for one amount' . PHP_EOL;
        }
    } else {
        echo '[' . date('Y-m-d H:i:s') . '] [WARNING] empty orderbooks or orderbooks has latency: ' . $get_orderbook_latency . PHP_EOL;
    }
}

function imitationMarketOrderSell(array $orderbook, float $amount, float $price_increment): array
{
    $am = $amount;
    $quote = 0;
    foreach ($orderbook['bids'] as $price_and_amount) {
        list($price, $amount) = $price_and_amount;
        if ($amount < $am) {
            $am -= $amount;
            $quote += $amount * $price;
        } else {
            $quote += $am * $price;
            return [
                'base' => $amount,
                'quote' => $quote,
                'price' => Math::incrementNumber($quote / $amount, $price_increment, true)
            ];
        }
    }

    return [];
}

function imitationMarketOrderBuy(array $orderbook, float $quote, float $price_increment): array
{
    $qu = $quote;
    $base = 0;
    foreach ($orderbook['asks'] as $price_and_amount) {
        list($price, $amount) = $price_and_amount;
        if ($amount * $price < $qu) {
            $qu -= $amount * $price;
            $base += $amount;
        } else {
            $base += $qu / $price;
            return [
                'base' => $base,
                'quote' => $quote,
                'price' => Math::incrementNumber($quote / $base, $price_increment, true)
            ];
        }
    }

    return [];
}

function reduceBalances(array &$balances, float $lower = 0.99): void
{
    foreach ($balances as $asset => $amount) {
        $balances[$asset]['free'] = $amount['free'] * $lower;
        $balances[$asset]['total'] = $amount['total'] * $lower;
    }
}