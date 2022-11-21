<?php

use Src\Crypto\Ccxt;
use Src\Crypto\Exchanges\Original\Bybit\Spot;
use Src\Support\Config;
use Src\Support\Math;
use Src\Support\Time;

require_once dirname(__DIR__, 3) . '/index.php';

if (!isset($argv[1]))
    die('Set parameter: symbol');

$symbol = $argv[1];

$config = Config::config('arbitrage', 'bybit');

$exchange = $config['exchange'];
$market_discovery = $config['market_discovery'];
$usleep = $config['usleep'];
$quote_asset = $config['quote_asset'];
$min_deal_amount = $config['min_deal_amount'];
$price_margin = $config['price_margin'];
$order_lifetime = $config['order_lifetime'];
$create_order_latency = $config['create_order_latency'];
$limitation_in_quote_asset = $config['limitation_in_quote_asset'];
$profits = $config['profits'];
$fees = $config['fees'];
$use_markets = $config['use_markets'];
$info_of_markets = $config['info_of_markets'];

$price_increment = $info_of_markets[$symbol]['price_increment'];
$amount_increment = $info_of_markets[$symbol]['amount_increment'];
$keys = getMemcachedKeys([$exchange, $market_discovery], $use_markets, $exchange);

$memcached = \Src\Databases\Memcached::init();

$api_keys_exchange = Config::file('keys', $exchange);
$api_keys_market_discovery = Config::file('keys', $market_discovery);

$ccxt_exchange = Spot::init($api_keys_exchange['api_public'], $api_keys_exchange['api_private'], ['symbols' => $use_markets]);;
$ccxt_market_discovery = Ccxt::init($market_discovery, api_key: $api_keys_market_discovery['api_public'], api_secret: $api_keys_market_discovery['api_private']);

while (true) {
    usleep($usleep);

    if ($data = $memcached->get($keys)) {
        list($orderbooks, $account_info, $mirror_trades_info) = formatMemcachedData($data);

        $open_orders = array_filter($account_info[$exchange]['open_orders'], fn($open_order) => $open_order['symbol'] == $symbol && $open_order['side'] == 'sell');

        if (count($open_orders) > 1) {
            foreach ($open_orders as $open_order) {
                if (Time::up(1, $open_order['id'], true)) {
                    $ccxt_exchange->cancelOrder($open_order['id']);
                    echo '[' . date('Y-m-d H:i:s') . '] [WARNING] Cancel order before counting because open orders more than one: ' . $open_order['id'] . PHP_EOL;
                }
            }
            unset($limit_exchange_sell_order);
        } else {
            if (isset($limit_exchange_sell_order)) {
                if (count(array_filter($open_orders, fn($open_order) => $open_order['id'] == $limit_exchange_sell_order['info']['id'])) == 1) {
                    if (
                        empty($orderbooks[$market_discovery][$symbol]) ||
                        !($imitation_market_order = imitationMarketOrderBuy($orderbooks[$market_discovery][$symbol], $limit_exchange_sell_order['counting']['market_discovery']['amount']['dirty'], $price_increment)) ||
                        !isOrderInRange($limit_exchange_sell_order, $imitation_market_order) ||
                        (microtime(true) - $limit_exchange_sell_order['info']['timestamp']) > $order_lifetime
                    ) {
                        if (Time::up(1, $limit_exchange_sell_order['info']['id'], true)) {
                            $ccxt_exchange->cancelOrder($limit_exchange_sell_order['info']['id']);
                            echo '[' . date('Y-m-d H:i:s') . '] [INFO] Cancel order: ' . $limit_exchange_sell_order['info']['id'] . PHP_EOL;
                        }
                        unset($limit_exchange_sell_order);
                    }
                } elseif ((microtime(true) - $limit_exchange_sell_order['info']['timestamp']) > 2)
                    unset($limit_exchange_sell_order);
            } elseif (Time::up($create_order_latency, 'create_order_exchange_sell')) {
                if (count($open_orders) > 0) {
                    foreach ($open_orders as $open_order) {
                        if (Time::up(1, $open_order['id'], true)) {
                            $ccxt_exchange->cancelOrder($open_order['id']);
                            echo '[' . date('Y-m-d H:i:s') . '] [WARNING] Cancel order before proofOrderbooks because open orders more than one: ' . $open_order['id'] . PHP_EOL;
                        }
                    }
                } else {
                    if (proofOrderbooks($orderbooks, $use_markets)) {
                        $balances[$market_discovery] = $account_info[$market_discovery]['balances'];
                        $balances[$exchange] = $account_info[$exchange]['balances'];

                        if (!empty($balances[$exchange]) && !empty($balances[$market_discovery])) {
                            $prices = getPrices($orderbooks, $market_discovery, $price_margin);

                            reduceBalances($balances[$market_discovery]);
                            reduceBalances($balances[$exchange]);
                            if (!empty($mirror_trades_info[$symbol]['buy'])) {
                                $balances[$market_discovery][$quote_asset]['free'] -= $mirror_trades_info[$symbol]['buy'] * $prices[$symbol]['sell'];
                                $balances[$market_discovery][$quote_asset]['total'] -= $mirror_trades_info[$symbol]['buy'] * $prices[$symbol]['sell'];
                            }

                            $positions = getPositions($balances, $prices, $exchange, $market_discovery, $quote_asset, $use_markets, $limitation_in_quote_asset);

                            $counting_sell = exchangeSellMarketDiscoveryBuy(
                                $orderbooks[$market_discovery][$symbol],
                                $positions[$symbol]['sell']['base_asset'],
                                $profits,
                                $fees[$exchange]['maker'],
                                $fees[$market_discovery]['taker'],
                                $amount_increment,
                                $price_increment
                            );

                            if ($counting_sell && $orderbooks[$exchange][$symbol]['bids'][0][0] > $counting_sell['exchange']['price'])
                                $counting_sell = exchangeSellMarketDiscoveryBuy(
                                    $orderbooks[$market_discovery][$symbol],
                                    Math::incrementNumber($positions[$symbol]['sell']['base_asset'] * 0.99, $amount_increment),
                                    $profits,
                                    $fees[$exchange]['taker'],
                                    $fees[$market_discovery]['taker'],
                                    $amount_increment,
                                    $price_increment
                                );

                            if ($counting_sell && $counting_sell['market_discovery']['confidence_interval']['price_max'] < $positions[$symbol]['sell']['price']) {
                                if ($counting_sell['exchange']['amount'] * $counting_sell['exchange']['price'] > $min_deal_amount) {
                                    if (
                                        $create_order = $ccxt_exchange->createOrder(
                                            $symbol,
                                            'limit',
                                            $counting_sell['exchange']['side'],
                                            Math::incrementNumber($counting_sell['exchange']['amount'], $amount_increment),
                                            max($counting_sell['exchange']['price'], Math::incrementNumber($orderbooks[$exchange][$symbol]['asks'][0][0] - 2 * $price_increment, $price_increment))
                                        )
                                    ) {
                                        $limit_exchange_sell_order = ['counting' => $counting_sell, 'info' => ['id' => $create_order['id'], 'filled' => 0, 'timestamp' => microtime(true)]];

                                        echo '[' . date('Y-m-d H:i:s') . '] [INFO] Order created: ' . $create_order['id'] . ' limit ' . $counting_sell['exchange']['side'] . ' ' . Math::incrementNumber($counting_sell['exchange']['amount'], $amount_increment) . ' ' . $counting_sell['exchange']['price'] . PHP_EOL;
                                        echo '[' . date('Y-m-d H:i:s') . '] [INFO] Order range: ' . $counting_sell['exchange']['price'] . ', ' . $counting_sell['market_discovery']['confidence_interval']['price_max'] . ', ' . $counting_sell['market_discovery']['confidence_interval']['price_min'] . PHP_EOL;
                                    } else
                                        echo '[' . date('Y-m-d H:i:s') . '] [WARNING] Can not create order!!!' . PHP_EOL;
                                }
                            } else
                                echo '[' . date('Y-m-d H:i:s') . '] [WARNING] May be not enough balance' . PHP_EOL;
                        }
                    } else
                        echo '[' . date('Y-m-d H:i:s') . '] [WARNING] Orderbooks not proofed: ' . implode(', ', array_keys($orderbooks)) . PHP_EOL;
                }
            }
        }
    } else
        echo '[' . date('Y-m-d H:i:s') . '] [WARNING] Memcached data is false!!!' . PHP_EOL;

    if ($data = $memcached->get($keys)) {
        list($orderbooks, $account_info) = formatMemcachedData($data);

        $open_orders = array_filter($account_info[$exchange]['open_orders'], fn($open_order) => $open_order['symbol'] == $symbol && $open_order['side'] == 'buy');

        if (count($open_orders) > 1) {
            foreach ($open_orders as $open_order) {
                if (Time::up(1, $open_order['id'], true)) {
                    $ccxt_exchange->cancelOrder($open_order['id']);
                    echo '[' . date('Y-m-d H:i:s') . '] [WARNING] Cancel order before counting because open orders more than one: ' . $open_order['id'] . PHP_EOL;
                }
            }
            unset($limit_exchange_buy_order);
        } else {
            if (isset($limit_exchange_buy_order)) {
                if (count(array_filter($open_orders, fn($open_order) => $open_order['id'] == $limit_exchange_buy_order['info']['id'])) == 1) {
                    if (
                        empty($orderbooks[$market_discovery][$symbol]) ||
                        !($imitation_market_order = imitationMarketOrderSell($orderbooks[$market_discovery][$symbol], $limit_exchange_buy_order['counting']['market_discovery']['quote']['dirty'])) ||
                        !isOrderInRange($limit_exchange_buy_order, $imitation_market_order) ||
                        (microtime(true) - $limit_exchange_buy_order['info']['timestamp']) > $order_lifetime
                    ) {
                        if (Time::up(1, $limit_exchange_buy_order['info']['id'], true)) {
                            $ccxt_exchange->cancelOrder($limit_exchange_buy_order['info']['id']);
                            echo '[' . date('Y-m-d H:i:s') . '] [INFO] Cancel order: ' . $limit_exchange_buy_order['info']['id'] . PHP_EOL;
                        }
                        unset($limit_exchange_buy_order);
                    }
                } elseif ((microtime(true) - $limit_exchange_buy_order['info']['timestamp']) > 2)
                    unset($limit_exchange_buy_order);
            } elseif (Time::up($create_order_latency, 'create_order_exchange_buy')) {
                if (count($open_orders) > 0) {
                    foreach ($open_orders as $open_order) {
                        if (Time::up(1, $open_order['id'], true)) {
                            $ccxt_exchange->cancelOrder($open_order['id']);
                            echo '[' . date('Y-m-d H:i:s') . '] [WARNING] Cancel order before proofOrderbooks because open orders more than one: ' . $open_order['id'] . PHP_EOL;
                        }
                    }
                } else {
                    if (proofOrderbooks($orderbooks, $use_markets)) {
                        $balances[$market_discovery] = $account_info[$market_discovery]['balances'];
                        $balances[$exchange] = $account_info[$exchange]['balances'];

                        if (!empty($balances[$exchange]) && !empty($balances[$market_discovery])) {
                            $prices = getPrices($orderbooks, $market_discovery, $price_margin);

                            reduceBalances($balances[$market_discovery]);
                            reduceBalances($balances[$exchange]);
                            if (!empty($mirror_trades_info[$symbol]['sell'])) {
                                $balances[$market_discovery][$quote_asset]['free'] -= $mirror_trades_info[$symbol]['sell'];
                                $balances[$market_discovery][$quote_asset]['total'] -= $mirror_trades_info[$symbol]['sell'];
                            }

                            $positions = getPositions($balances, $prices, $exchange, $market_discovery, $quote_asset, $use_markets, $limitation_in_quote_asset);

                            $counting_buy = exchangeBuyMarketDiscoverySell(
                                $orderbooks[$market_discovery][$symbol],
                                $positions[$symbol]['buy']['quote_asset'],
                                $profits,
                                $fees[$exchange]['maker'],
                                $fees[$market_discovery]['taker'],
                                $amount_increment,
                                $price_increment
                            );

                            if ($counting_buy && $orderbooks[$exchange][$symbol]['asks'][0][0] < $counting_buy['exchange']['price'])
                                $counting_buy = exchangeBuyMarketDiscoverySell(
                                    $orderbooks[$market_discovery][$symbol],
                                    $positions[$symbol]['buy']['quote_asset'] * 0.99,
                                    $profits,
                                    $fees[$exchange]['taker'],
                                    $fees[$market_discovery]['taker'],
                                    $amount_increment,
                                    $price_increment
                                );

                            if ($counting_buy && $counting_buy['market_discovery']['confidence_interval']['price_min'] > $positions[$symbol]['buy']['price']) {
                                if ($counting_buy['exchange']['quote'] > $min_deal_amount) {
                                    if (
                                        $create_order = $ccxt_exchange->createOrder(
                                            $symbol,
                                            'limit',
                                            $counting_buy['exchange']['side'],
                                            Math::incrementNumber($counting_buy['exchange']['amount']['dirty'], $amount_increment),
                                            min($counting_buy['exchange']['price'], Math::incrementNumber($orderbooks[$exchange][$symbol]['bids'][0][0] + 2 * $price_increment, $price_increment))
                                        )
                                    ) {
                                        $limit_exchange_buy_order = ['counting' => $counting_buy, 'info' => ['id' => $create_order['id'], 'filled' => 0, 'timestamp' => microtime(true)]];

                                        echo '[' . date('Y-m-d H:i:s') . '] [INFO] Order created: ' . $create_order['id'] . ' limit ' . $counting_buy['exchange']['side'] . ' ' . Math::incrementNumber($counting_buy['exchange']['amount']['dirty'], $amount_increment) . ' ' . $counting_buy['exchange']['price'] . PHP_EOL;
                                        echo '[' . date('Y-m-d H:i:s') . '] [INFO] Order range: ' . $counting_buy['exchange']['price'] . ', ' . $counting_buy['market_discovery']['confidence_interval']['price_max'] . ', ' . $counting_buy['market_discovery']['confidence_interval']['price_min'] . PHP_EOL;
                                    } else
                                        echo '[' . date('Y-m-d H:i:s') . '] [WARNING] Can not create order!!!' . PHP_EOL;
                                }
                            } else
                                echo '[' . date('Y-m-d H:i:s') . '] [WARNING] May be not enough balance' . PHP_EOL;
                        }
                    } else
                        echo '[' . date('Y-m-d H:i:s') . '] [WARNING] Orderbooks not proofed: ' . implode(', ', array_keys($orderbooks)) . PHP_EOL;
                }
            }
        }
    } else
        echo '[' . date('Y-m-d H:i:s') . '] [WARNING] Memcached data is false!!!' . PHP_EOL;

    // VERY IMPORTANT RESET UNUSED TIME PREFIX AND GIVE LIST USED PREFIX
    Time::update(['create_order_exchange_sell', 'create_order_exchange_buy']);
}

function isOrderInRange($limit_exchange_order, $imitation_market_order): bool
{
    return $imitation_market_order['price'] < $limit_exchange_order['counting']['market_discovery']['confidence_interval']['price_max'] &&
        $imitation_market_order['price'] > $limit_exchange_order['counting']['market_discovery']['confidence_interval']['price_min'];
}

function getPositions(array $balances, array $prices, string $exchange, string $market_discovery, string $quote_asset, array $markets, float $limitation_in_quote_asset = 10000): array
{
    $sum = [$exchange => 0, $market_discovery => 0];
    $balances_in_quote_asset = [];

    foreach ($balances[$exchange] as $asset => $amount) {
        if ($asset != $quote_asset) {
            $balances_in_quote_asset[$exchange][$asset] = $amount['total'] * $prices[$asset . '/' . $quote_asset]['sell'];
            $sum[$exchange] += $balances_in_quote_asset[$exchange][$asset];
        } else
            $balances_in_quote_asset[$exchange][$asset] = $amount['total'];
    }

    foreach ($balances[$market_discovery] as $asset => $amount) {
        if ($asset != $quote_asset) {
            $balances_in_quote_asset[$market_discovery][$asset] = $amount['total'] * $prices[$asset . '/' . $quote_asset]['buy'];
            $sum[$market_discovery] += $balances_in_quote_asset[$market_discovery][$asset];
        } else
            $balances_in_quote_asset[$market_discovery][$asset] = $amount['total'];
    }

    foreach ($markets as $market) {
        list($base_asset_market) = explode('/', $market);

        $sell_mins = [
            $balances_in_quote_asset[$exchange][$base_asset_market],
            $limitation_in_quote_asset
        ];
        if ($sum[$exchange] != 0)
            $sell_mins[] = $balances[$market_discovery][$quote_asset]['free'] * $balances_in_quote_asset[$exchange][$base_asset_market] / $sum[$exchange];
        $can_sell_in_quote_asset = min(...$sell_mins);

        $buy_mins = [
            $balances_in_quote_asset[$market_discovery][$base_asset_market],
            $limitation_in_quote_asset
        ];
        if ($sum[$market_discovery] != 0)
            $buy_mins[] = $balances[$exchange][$quote_asset]['free'] * $balances_in_quote_asset[$market_discovery][$base_asset_market] / $sum[$market_discovery];
        $can_buy_in_quote_asset = min(...$buy_mins);

        $positions[$market] = [
            'sell' => [
                'base_asset' => $can_sell_in_quote_asset / $prices[$base_asset_market . '/' . $quote_asset]['sell'],
                'quote_asset' => $can_sell_in_quote_asset,
                'price' => $prices[$base_asset_market . '/' . $quote_asset]['sell']
            ],
            'buy' => [
                'base_asset' => $can_buy_in_quote_asset / $prices[$base_asset_market . '/' . $quote_asset]['buy'],
                'quote_asset' => $can_buy_in_quote_asset,
                'price' => $prices[$base_asset_market . '/' . $quote_asset]['buy']
            ]
        ];
    }

    return $positions ?? [];
}

function exchangeSellMarketDiscoveryBuy(array $orderbook, float $must_get_amount, array $profits, float $fee_exchange, float $fee_market_discovery, float $amount_increment, float $price_increment): array
{
    $counting['market_discovery']['amount']['clean'] = $must_get_amount;
    $counting['market_discovery']['amount']['dirty'] = Math::incrementNumber($must_get_amount / (1 - $fee_market_discovery / 100), $amount_increment, true);

    if ($imitation_market_order = imitationMarketOrderBuy($orderbook, $counting['market_discovery']['amount']['dirty'], $price_increment)) {
        $counting['market_discovery']['quote'] = $imitation_market_order['quote'];
        $counting['market_discovery']['price'] = $imitation_market_order['price'];
        $counting['market_discovery']['symbol'] = $orderbook['symbol'];
        $counting['market_discovery']['side'] = 'buy';

        $counting['exchange']['amount'] = $must_get_amount;
        $counting['exchange']['price'] = Math::incrementNumber($counting['market_discovery']['price'] / ((1 - $fee_market_discovery / 100) * (1 - $fee_exchange / 100) * (1 - $profits['optimal'] / 100)), $price_increment);

        $counting['exchange']['quote']['dirty'] = $counting['exchange']['amount'] * $counting['exchange']['price'];
        $counting['exchange']['quote']['clean'] = $counting['exchange']['quote']['dirty'] * (1 - $fee_exchange / 100);
        $counting['exchange']['symbol'] = $orderbook['symbol'];
        $counting['exchange']['side'] = 'sell';

        $counting['market_discovery']['confidence_interval']['price_max'] = $counting['exchange']['price'] * (1 - $fee_market_discovery / 100) * (1 - $fee_exchange / 100) * (1 - $profits['min'] / 100);
        $counting['market_discovery']['confidence_interval']['price_min'] = $counting['exchange']['price'] * (1 - $fee_market_discovery / 100) * (1 - $fee_exchange / 100) * (1 - $profits['max'] / 100);

        return $counting;
    }

    return [];
}

function exchangeBuyMarketDiscoverySell(array $orderbook, float $must_get_quote, array $profits, float $fee_exchange, float $fee_market_discovery, float $amount_increment, float $price_increment): array
{
    $counting['market_discovery']['quote']['clean'] = $must_get_quote;
    $counting['market_discovery']['quote']['dirty'] = $must_get_quote / (1 - $fee_market_discovery / 100);

    if ($imitation_market_order = imitationMarketOrderSell($orderbook, $counting['market_discovery']['quote']['dirty'])) {
        $counting['market_discovery']['amount'] = Math::incrementNumber($imitation_market_order['base'], $amount_increment);
        $counting['market_discovery']['price'] = Math::incrementNumber($imitation_market_order['price'], $price_increment, true);
        $counting['market_discovery']['symbol'] = $orderbook['symbol'];
        $counting['market_discovery']['side'] = 'sell';

        $counting['exchange']['quote'] = $must_get_quote;
        $counting['exchange']['price'] = Math::incrementNumber($counting['market_discovery']['price'] * (1 - $fee_market_discovery / 100) * (1 - $fee_exchange / 100 - $profits['optimal'] / 100), $price_increment);

        $counting['exchange']['amount']['dirty'] = Math::incrementNumber($counting['exchange']['quote'] / $counting['exchange']['price'], $amount_increment, true);
        $counting['exchange']['amount']['clean'] = $counting['exchange']['amount']['dirty'] * (1 - $fee_exchange / 100);
        $counting['exchange']['symbol'] = $orderbook['symbol'];
        $counting['exchange']['side'] = 'buy';

        $counting['market_discovery']['confidence_interval']['price_max'] = $counting['exchange']['price'] / ((1 - $fee_market_discovery / 100) * (1 - $fee_exchange / 100 - $profits['max'] / 100));
        $counting['market_discovery']['confidence_interval']['price_min'] = $counting['exchange']['price'] / ((1 - $fee_market_discovery / 100) * (1 - $fee_exchange / 100 - $profits['min'] / 100));

        return $counting;
    }

    return [];
}

function imitationMarketOrderBuy(array $orderbook, float $must_amount, float $price_increment): array
{
    $am = $must_amount;
    $quote = 0;
    foreach ($orderbook['asks'] as $price_and_amount) {
        list($price, $amount) = $price_and_amount;

        if ($amount < $am) {
            $am -= $amount;

            $quote += $amount * $price;
        } else {
            $quote += $am * $price;

            return [
                'quote' => $quote,
                'price' => Math::incrementNumber($quote / $must_amount, $price_increment, true)
            ];
        }
    }

    return [];
}

function imitationMarketOrderSell(array $orderbook, float $must_quote): array
{
    $am = $must_quote;
    $base = 0;
    foreach ($orderbook['bids'] as $price_and_amount) {
        list($price, $amount) = $price_and_amount;

        if ($amount * $price < $am) {
            $am -= $amount * $price;

            $base += $amount;
        } else {
            $base += $am / $price;

            return [
                'base' => $base,
                'price' => $must_quote / $base
            ];
        }
    }

    return [];
}

function getPrices(array $orderbooks, string $market_discovery, float $price_margin = 0.1): array
{
    foreach ($orderbooks[$market_discovery] as $market => $orderbook)
        $prices[$market] = [
            'buy' => $orderbook['bids'][0][0] * (1 - $price_margin / 100),
            'sell' => $orderbook['asks'][0][0] * (1 + $price_margin / 100),
        ];

    return $prices ?? [];
}

function getMemcachedKeys(array $exchanges, array $markets, string $mirror_trades_exchange): array
{
    $keys = ['is_good_arbitrage', 'mirrorTrades_' . $mirror_trades_exchange];

    foreach ($exchanges as $exchange) {
        $keys[] = 'accountInfo_' . $exchange;

        foreach ($markets as $market)
            $keys[] = $exchange . '_' . $market;
    }

    return $keys;
}

function formatMemcachedData(array $data): array
{
    foreach ($data as $key => $datum) {
        if ($key == 'is_good_arbitrage') {
            $is_good_arbitrage = ((microtime(true) - $datum['timestamp']) < 300) ? $datum['data'] : false;
        } elseif (str_contains($key, 'accountInfo_')) {
            list(, $exchange) = explode('_', $key);
            
            $account_info[$exchange] = [
                'balances' => $datum['data']['balances'],
                'open_orders' => $datum['data']['open_orders'] ?? []
            ];
        } elseif (str_contains($key, 'mirrorTrades_')) {
            $mirror_trades_info = $datum['data']['leftovers'];
        } else {
            $orderbooks[$datum['data']['exchange']][$datum['data']['symbol']] = $datum['data'];
        }
    }

    return [$orderbooks ?? [], $account_info ?? [], $mirror_trades_info ?? [], $is_good_arbitrage ?? false];
}

function proofOrderbooks(array $orderbooks, array $markets): bool
{
    if (empty($orderbooks))
        return false;

    foreach ($orderbooks as $orderbook)
        foreach ($markets as $market)
            if (!array_key_exists($market, $orderbook))
                return false;

    return true;
}

function reduceBalances(array &$balances, float $lower = 0.99): void
{
    foreach ($balances as $asset => $amount) {
        $balances[$asset]['free'] = $amount['free'] * $lower;
        $balances[$asset]['total'] = $amount['total'] * $lower;
    }
}
