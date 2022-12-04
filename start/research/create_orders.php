<?php

use Src\Crypto\Exchanges\Binance;
use Src\Crypto\Exchanges\Bybit;
use Src\Support\Config;
use Src\Support\Math;

require_once dirname(__DIR__, 2) . '/index.php';

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
$fee_one = $fees['binance'];
$fee_two = $fees['bybit'];
list($base_asset, $quote_asset) = explode('/', $symbol);

$exchange_one = Binance::init('', '');
$exchange_two = Bybit::init('', '');

while (true) {
    sleep($sleep);

    $time_start = microtime(true);
    $orderbook_one = $exchange_one->getOrderbook('BTCUSDT');
    $orderbook_two = $exchange_two->getOrderbook('BTCUSDT');
    $get_orderbook_latency = microtime(true) - $time_start;

    if ($orderbook_one && $orderbook_two && ($get_orderbook_latency <= $max_get_orderbook_latency)) {
            $imitation_base_asset_sell_one = imitationMarketOrderSell($orderbook_one, $balances_one[$base_asset]['free'] * 2, $price_increment);
            $imitation_quote_asset_sell_two = imitationMarketOrderBuy($orderbook_two, $balances_two[$quote_asset]['free'] * 2, $price_increment);
            if ($imitation_base_asset_sell_one['base'] < $imitation_quote_asset_sell_two['base']) {
                $imitation_quote_asset_sell_two = imitationMarketOrderBuy($orderbook_two, $imitation_base_asset_sell_one['quote'], $price_increment);
                $profit = round(($imitation_quote_asset_sell_two['base'] - $imitation_base_asset_sell_one['base']) / ($imitation_quote_asset_sell_two['base'] + $imitation_base_asset_sell_one['base']) * 100, 8);
            } else {
                $imitation_base_asset_sell_one = imitationMarketOrderSell($orderbook_one, $imitation_quote_asset_sell_two['base'], $price_increment);
                $profit = round(($imitation_base_asset_sell_one['quote'] - $imitation_quote_asset_sell_two['quote']) / ($imitation_base_asset_sell_one['quote'] + $imitation_quote_asset_sell_two['quote']) * 100, 8);
            }
            if ($profit > $min_profit) {
                $imitation_base_asset_sell_for_amount_one = imitationMarketOrderSell($orderbook_one, $balances_one[$base_asset]['free'], $price_increment);
                $imitation_quote_asset_for_amount_sell_two = imitationMarketOrderBuy($orderbook_two, $balances_two[$quote_asset]['free'], $price_increment);
                if (min($imitation_base_asset_sell_for_amount_one['quote'], $imitation_quote_asset_for_amount_sell_two['quote']) > $min_deal_amount) {
                    $amount = Math::incrementNumber(min($imitation_base_asset_sell_for_amount_one['base'], $imitation_quote_asset_for_amount_sell_two['base']), $amount_increment);
                    echo '[' . date('Y-m-d H:i:s') . '] [INFO] [CREATE ORDERS]. ONE SELL. Amount: ' . $amount . '. Buy: ' . $imitation_quote_asset_for_amount_sell_two['price'] . '. Sell: ' . $imitation_base_asset_sell_for_amount_one['price'] . PHP_EOL;
                }
            }

            $imitation_base_asset_sell_two = imitationMarketOrderSell($orderbook_two, $balances_two[$base_asset]['free'] * 2, $price_increment);
            $imitation_quote_asset_sell_one = imitationMarketOrderBuy($orderbook_one, $balances_one[$quote_asset]['free'] * 2, $price_increment);
            if ($imitation_base_asset_sell_two['base'] < $imitation_quote_asset_sell_one['base']) {
                $imitation_quote_asset_sell_one = imitationMarketOrderBuy($orderbook_one, $imitation_base_asset_sell_two['quote'], $price_increment);
                $profit = round(($imitation_quote_asset_sell_one['base'] - $imitation_base_asset_sell_two['base']) / ($imitation_quote_asset_sell_one['base'] + $imitation_base_asset_sell_two['base']) * 100, 8);
            } else {
                $imitation_base_asset_sell_two = imitationMarketOrderSell($orderbook_two, $imitation_quote_asset_sell_one['base'], $price_increment);
                $profit = round(($imitation_base_asset_sell_two['quote'] - $imitation_quote_asset_sell_one['quote']) / ($imitation_base_asset_sell_two['quote'] + $imitation_quote_asset_sell_one['quote']) * 100, 8);
            }
            if ($profit > $min_profit) {
                $imitation_base_asset_sell_for_amount_two = imitationMarketOrderSell($orderbook_two, $balances_two[$base_asset]['free'], $price_increment);
                $imitation_quote_asset_sell_for_amount_one = imitationMarketOrderBuy($orderbook_one, $balances_one[$quote_asset]['free'], $price_increment);
                if (min($imitation_base_asset_sell_for_amount_two['quote'], $imitation_quote_asset_sell_for_amount_one['quote']) > $min_deal_amount) {
                    $amount = Math::incrementNumber(min($imitation_base_asset_sell_for_amount_two['base'], $imitation_quote_asset_sell_for_amount_one['base']), $amount_increment);
                    echo '[' . date('Y-m-d H:i:s') . '] [INFO] [CREATE ORDERS]. TWO SELL. Amount: ' . $amount . '. Buy: ' . $imitation_quote_asset_sell_for_amount_one['price'] . '. Sell: ' . $imitation_base_asset_sell_for_amount_two['price'] . '. Profit: ' . $profit . PHP_EOL;
                }
            }
    } else {
        echo '[' . date('Y-m-d H:i:s') . '] [WARNING] empty orderbooks or orderbooks has latency: ' . $get_orderbook_latency . PHP_EOL;
    }

    if (empty($balances_one))
        $balances_one = $exchange_one->getBalances($assets);
    if (empty($balances_two))
        $balances_two = $exchange_two->getBalances($assets);
}

function imitationMarketOrderSell(array $orderbook, float $must_amount, float $price_increment): array
{
    $am = $must_amount;
    $quote = 0;
    foreach ($orderbook['bids'] as $price_and_amount) {
        list($price, $amount) = $price_and_amount;
        if ($amount < $am) {
            $am -= $amount;
            $quote += $amount * $price;
        } else {
            $quote += $am * $price;
            return [
                'base' => $must_amount,
                'quote' => $quote,
                'price' => Math::incrementNumber($quote / $must_amount, $price_increment, true)
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