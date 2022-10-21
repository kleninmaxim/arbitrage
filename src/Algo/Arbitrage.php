<?php

namespace Src\Algo;

use Exception;
use Src\Crypto\Ccxt;
use Src\Support\Log;
use Src\Support\Math;
use Src\Support\Time;

class Arbitrage
{
    private Ccxt $exchange;
    private Ccxt $market_discovery;
    private array $start_balances;
    private array $balances;
    private array $open_orders;
    private array $mirror_orders = [];
    private bool $need_update_balance_exchange = true;
    private bool $need_update_balance_market_discovery = true;
    private array $positions;

    /**
     * @throws Exception
     */
    public function __construct(Ccxt $exchange, Ccxt $market_discovery, array $assets = null)
    {
        $this->exchange = $exchange;
        $this->market_discovery = $market_discovery;
        $this->update($assets);
        $this->start_balances = $this->balances;
        $this->exchange->cancelAllOrder();
        $this->market_discovery->cancelAllOrder();
    }

    /**
     * @throws Exception
     */
    public static function init(...$parameters): static
    {
        return new static(...$parameters);
    }

    public function update(...$balances_parameters): void
    {
        if ($this->need_update_balance_exchange) {
            $this->updateExchangeBalances(...$balances_parameters);
            $this->updateOpenOrders();
            $this->need_update_balance_exchange = false;
        }

        if ($this->need_update_balance_market_discovery) {
            $this->updateMarketDiscoveryBalances(...$balances_parameters);
            $this->need_update_balance_market_discovery = false;
        }
    }

    public function updateExchangeBalances(array $assets = null, bool $lower_balance = true, float $lower = 0.99): void
    {
        try {
            $this->balances[$this->exchange->name] = $this->exchange->getBalances($assets);
        } catch (Exception $e) {
            if (Time::up(5, 'getBalances', true))
                Log::error($e, ['$assets' => $assets, '$lower_balance' => $lower_balance, '$lower' => $lower]);
        }
    }

    public function updateMarketDiscoveryBalances(array $assets = null, bool $lower_balance = true, float $lower = 0.99): void
    {
        try {
            $this->balances[$this->market_discovery->name] = $this->market_discovery->getBalances($assets);

            if ($lower_balance)
                foreach ($this->balances[$this->market_discovery->name] as $asset => $amount)
                    $this->balances[$this->market_discovery->name][$asset]['free'] = $amount['free'] * $lower;
        } catch (Exception $e) {
            if (Time::up(5, 'getBalances', true))
                Log::error($e, ['$assets' => $assets, '$lower_balance' => $lower_balance, '$lower' => $lower]);
        }
    }

    public function updateOpenOrders(): void
    {
        try {
            $this->open_orders = $this->exchange->getOpenOrders();
        } catch (Exception $e) {
            if (Time::up(5, 'getOpenOrders', true))
                Log::error($e);
        }
    }

    public function formatOrderbook(array $memcached_orderbooks): array
    {
        foreach ($memcached_orderbooks['data']['orderbook'] as $memcached_orderbook)
            $orderbooks[$memcached_orderbook['exchange']][$memcached_orderbook['symbol']] = $memcached_orderbook;

        return $orderbooks ?? [];
    }

    public function proofOrderbooks(array $orderbooks, array $use_markets): bool
    {
        foreach ($orderbooks as $orderbook)
            foreach ($use_markets as $use_market)
                if (empty($orderbook[$use_market]))
                    return false;

        return true;
    }

    public function getPrices(array $orderbooks, string $market_discovery): array
    {
        foreach ($orderbooks[$market_discovery] as $market => $orderbook) {
            $prices[$market]['sell'] = $orderbook['bids'][0][0];
            $prices[$market]['buy'] = $orderbook['asks'][0][0];
        }

        return $prices ?? [];
    }

    public function updatePositions(array $prices, string $exchange, string $market_discovery, string $quote_asset, $markets): void
    {
        $sum = [
            $exchange => 0,
            $market_discovery => 0
        ];
        $balances_in_quote_asset = [];

        foreach ($this->balances[$exchange] as $asset => $amount) {
            if ($asset != $quote_asset) {
                $balances_in_quote_asset[$exchange][$asset] = $amount['total'] * $prices[$asset . '/' . $quote_asset]['sell'];
                $sum[$exchange] += $balances_in_quote_asset[$exchange][$asset];
            } else
                $balances_in_quote_asset[$exchange][$asset] = $amount['total'];
        }

        foreach ($this->balances[$market_discovery] as $asset => $amount) {
            if ($asset != $quote_asset) {
                $balances_in_quote_asset[$market_discovery][$asset] = $amount['total'] * $prices[$asset . '/' . $quote_asset]['buy'];
                $sum[$market_discovery] += $balances_in_quote_asset[$market_discovery][$asset];
            } else
                $balances_in_quote_asset[$market_discovery][$asset] = $amount['total'];
        }

        foreach ($markets as $market) {
            list($base_asset_market) = explode('/', $market);

            $used_quote_asset_in_market_discovery = 0;
            $used_quote_asset_in_exchange = 0;
            foreach ($this->open_orders as $open_order)
                if ($open_order['symbol'] != $market) {
                    if ($open_order['side'] == 'sell')
                        $used_quote_asset_in_market_discovery += $open_order['remaining'] * $open_order['price'];

                    if ($open_order['side'] == 'buy')
                        $used_quote_asset_in_exchange += $open_order['remaining'] * $open_order['price'];
                }

            // sell
            $can_sell_in_quote_asset = min(
                $this->balances[$market_discovery][$quote_asset]['total'] * $balances_in_quote_asset[$exchange][$base_asset_market] / $sum[$exchange],
                $balances_in_quote_asset[$exchange][$base_asset_market],
                $this->balances[$market_discovery][$quote_asset]['total'] - $used_quote_asset_in_market_discovery
            );

            $positions[$market]['sell'] = [
                'base_asset' => $can_sell_in_quote_asset / $prices[$base_asset_market . '/' . $quote_asset]['sell'],
                'quote_asset' => $can_sell_in_quote_asset,
                'price' => $prices[$base_asset_market . '/' . $quote_asset]['sell']
            ];

            // buy
            $can_buy_in_quote_asset = min(
                $this->balances[$exchange][$quote_asset]['total'] * $balances_in_quote_asset[$market_discovery][$base_asset_market] / $sum[$market_discovery],
                $balances_in_quote_asset[$market_discovery][$base_asset_market],
                $this->balances[$exchange][$quote_asset]['total'] - $used_quote_asset_in_exchange
            );

            $positions[$market]['buy'] = [
                'base_asset' => $can_buy_in_quote_asset / $prices[$base_asset_market . '/' . $quote_asset]['buy'],
                'quote_asset' => $can_buy_in_quote_asset,
                'price' => $prices[$base_asset_market . '/' . $quote_asset]['buy']
            ];
        }

        $this->positions = $positions ?? [];
    }

    /**
     * @throws Exception
     */
    public function checkOpenOrdersAndCreateMarketOrders(array $orderbooks, array $prices, float $min_deal_amount, array $markets): void
    {
        foreach ($this->mirror_orders as $order_id => $mirror_order) {
            $exist = false;

            foreach ($this->open_orders as $open_order)
                if ($open_order['id'] == $order_id) {
                    $side = ($open_order['side'] == 'sell') ? 'buy' : 'sell';

                    $this->createMarketOrderIfPartialFilled($open_order, $mirror_order, $prices, $side, $min_deal_amount, $markets[$open_order['symbol']]['amount_precision'], $markets[$open_order['symbol']]['price_precision']);

                    $this->cancelOrderIfPriceNotInConfidenceInterval(
                        $order_id,
                        $open_order['symbol'],
                        $orderbooks[$this->market_discovery->name][$open_order['symbol']],
                        $mirror_order,
                        $side,
                        $markets[$open_order['symbol']]['amount_precision'],
                        $markets[$open_order['symbol']]['price_precision']
                    );

                    $exist = true;
                    break;
                }

            if (!$exist) {
                $symbol = $mirror_order['counting']['exchange']['market'];

                $this->getStatusAndCreateMarketOrderIfPartialFilled($order_id, $symbol, $mirror_order, $prices, $min_deal_amount, $markets[$symbol]['amount_precision'], $markets[$symbol]['price_precision']);
            }
        }
    }

    /**
     * @throws Exception
     */
    public function createMarketOrderIfPartialFilled(array $open_order, array $mirror_order, array $prices, string $side, float $min_deal_amount, float $amount_precision, float $price_precision): void
    {
        if (!Math::compareFloats($open_order['filled'], $mirror_order['filled'])) {
            $amount = Math::incrementNumber($open_order['filled'] - $mirror_order['filled'], $amount_precision);
            $price = Math::incrementNumber($prices[$open_order['symbol']][$side], $price_precision);

            if ($amount * $price > $min_deal_amount) {
                $this->market_discovery->createOrder(
                    $open_order['symbol'],
                    'market',
                    $side,
                    $amount
                );

                $this->mirror_orders[$open_order['id']]['filled'] = $open_order['filled'];

                $this->need_update_balance_exchange = true;
                $this->need_update_balance_market_discovery = true;
            }
        }
    }

    /**
     * @throws Exception
     */
    public function cancelOrderIfPriceNotInConfidenceInterval(string $order_id, string $symbol, array $orderbook, array $mirror_order, string $side, float $amount_precision, float $price_precision): void
    {
        if ($side == 'sell')
            $imitation_order = $this->imitationMarketOrderSell(
                $orderbook,
                $mirror_order['counting']['market_discovery']['amount'] * $mirror_order['counting']['market_discovery']['price'],
                $amount_precision,
                $price_precision
            );

        if ($side == 'buy')
            $imitation_order = $this->imitationMarketOrderBuy(
                $orderbook,
                $mirror_order['counting']['market_discovery']['amount'],
                $price_precision
            );

        if (
            empty($imitation_order['price']) ||
            $imitation_order['price'] > $mirror_order['counting']['market_discovery']['confidence_interval']['price_max'] ||
            $imitation_order['price'] < $mirror_order['counting']['market_discovery']['confidence_interval']['price_min']
        ) {
            $this->exchange->cancelOrder($order_id, $symbol);

            $this->need_update_balance_exchange = true;
        }
    }

    /**
     * @throws Exception
     */
    public function getStatusAndCreateMarketOrderIfPartialFilled($order_id, $symbol, $mirror_order, $prices, $min_deal_amount, float $amount_precision, float $price_precision): void
    {
        $order = $this->market_discovery->getOrderStatus($order_id, $symbol);

        if (!Math::compareFloats($order['filled'], $mirror_order['filled'])) {
            $side = ($order['side'] == 'sell') ? 'buy' : 'sell';
            $amount = Math::incrementNumber($order['filled'] - $mirror_order['filled'], $amount_precision);
            $price = Math::incrementNumber($prices[$order['symbol']][$side], $price_precision);

            if ($amount * $price > $min_deal_amount)
                $this->market_discovery->createOrder(
                    $order['symbol'],
                    'market',
                    $side,
                    $amount
                );

            $this->need_update_balance_exchange = true;
            $this->need_update_balance_market_discovery = true;
        }

        unset($this->mirror_orders[$order_id]);
    }

    /**
     * @throws Exception
     */
    public function createLimitOrder($orderbooks, $position, $symbol, $min_deal_amount, $profits, $fees, $markets): void
    {
        if ($this->canCreate($position, $symbol, 'sell', $min_deal_amount))
            $this->createMirrorOrder(
                $this->formatExchangeOrder(
                    $this->exchangeSellMarketDiscoveryBuy(
                        $orderbooks[$this->market_discovery->name][$symbol],
                        $position['sell']['base_asset'],
                        $profits,
                        $fees[$this->exchange->name]['taker'],
                        $fees[$this->market_discovery->name]['taker'],
                        $markets[$symbol]['amount_precision'],
                        $markets[$symbol]['price_precision']
                    ),
                    $symbol
                )
            );

        if ($this->canCreate($position, $symbol, 'buy', $min_deal_amount))
            $this->createMirrorOrder(
                $this->formatExchangeOrder(
                    $this->exchangeBuyMarketDiscoverySell(
                        $orderbooks[$this->market_discovery->name][$symbol],
                        $position['buy']['quote_asset'],
                        $profits,
                        $fees[$this->exchange->name]['taker'],
                        $fees[$this->market_discovery->name]['taker'],
                        $markets[$symbol]['amount_precision'],
                        $markets[$symbol]['price_precision']
                    ),
                    $symbol
                )
            );
    }

    /**
     * @throws Exception
     */
    public function createMirrorOrder(array $create_order): void
    {
        $order = $this->exchange->createOrder(
            $create_order['exchange']['market'],
            $create_order['exchange']['type'],
            $create_order['exchange']['side'],
            $create_order['exchange']['amount'],
            $create_order['exchange']['price']
        );

        $this->mirror_orders[$order['id']] = [
            'counting' => $create_order,
            'filled' => 0
        ];

        $this->need_update_balance_exchange = true;
    }

    public function canCreate(array $position, string $symbol, string $type, float $min_deal_amount): bool
    {
        return !$this->getOpenOrder($symbol, $type) && $position[$type]['quote_asset'] >= $min_deal_amount;
    }

    /**
     * @throws Exception
     */
    public function createLimitOrders(array $orderbooks, array $use_markets, float $min_deal_amount, array $profits, array $fees, array $markets): void
    {
        foreach ($use_markets as $symbol)
            $this->createLimitOrder($orderbooks, $this->positions[$symbol], $symbol, $min_deal_amount, $profits, $fees, $markets);
    }

    public function getOpenOrder(string $market, string $side): array|null
    {
        $open_orders_for_market = array_filter(
            $this->open_orders,
            fn($open_order) => $open_order['symbol'] == $market && $open_order['side'] == $side
        );

        return array_shift($open_orders_for_market);
    }

    public function formatExchangeOrder(array $counting, string $market): array
    {
        return [
            'market_discovery' => [
                'market' => $market,
                'type' => 'market',
                'side' => $counting['market_discovery']['side'],
                'amount' => ($counting['market_discovery']['side'] == 'sell') ? $counting['market_discovery']['amount'] : $counting['market_discovery']['amount']['dirty'],
                'price' => $counting['market_discovery']['price'],
                'confidence_interval' => $counting['market_discovery']['confidence_interval']
            ],
            'exchange' => [
                'market' => $market,
                'type' => 'limit',
                'side' => $counting['exchange']['side'],
                'amount' => ($counting['exchange']['side'] == 'sell') ? $counting['exchange']['amount'] : $counting['exchange']['amount']['dirty'],
                'price' => $counting['exchange']['price']
            ],
            'counting' => $counting
        ];
    }

    public function exchangeSellMarketDiscoveryBuy(array $orderbook, float $must_get_amount, array $profits, float $fee_exchange, float $fee_market_discovery, float $amount_precision, float $price_precision): array
    {
        $counting['market_discovery']['amount']['clean'] = $must_get_amount;
        $counting['market_discovery']['amount']['dirty'] = Math::incrementNumber($must_get_amount / (1 - $fee_market_discovery / 100), $amount_precision, true);

        $imitation_market_order = $this->imitationMarketOrderBuy($orderbook, $counting['market_discovery']['amount']['dirty'], $price_precision);

        $counting['market_discovery']['quote'] = $imitation_market_order['quote'];
        $counting['market_discovery']['price'] = $imitation_market_order['price'];
        $counting['market_discovery']['side'] = 'buy';

        $counting['exchange']['amount'] = $must_get_amount;
        $counting['exchange']['price'] = Math::incrementNumber($counting['market_discovery']['price'] / ((1 - $fee_market_discovery / 100) * (1 - $fee_exchange / 100) * (1 - $profits['optimal'] / 100)), $price_precision);

        $counting['exchange']['quote']['dirty'] = $counting['exchange']['amount'] * $counting['exchange']['price'];
        $counting['exchange']['quote']['clean'] = $counting['exchange']['quote']['dirty'] * (1 - $fee_exchange / 100);
        $counting['exchange']['side'] = 'sell';

        $counting['market_discovery']['confidence_interval']['price_max'] = $counting['exchange']['price'] * (1 - $fee_market_discovery / 100) * (1 - $fee_exchange / 100) * (1 - $profits['min'] / 100);
        $counting['market_discovery']['confidence_interval']['price_min'] = $counting['exchange']['price'] * (1 - $fee_market_discovery / 100) * (1 - $fee_exchange / 100) * (1 - $profits['max'] / 100);

        return $counting;
    }

    public function exchangeBuyMarketDiscoverySell(array $orderbook, float $must_get_quote, array $profits, float $fee_exchange, float $fee_market_discovery, float $amount_precision, float $price_precision): array
    {
        $counting['market_discovery']['quote']['clean'] = $must_get_quote;
        $counting['market_discovery']['quote']['dirty'] = $must_get_quote / (1 - $fee_market_discovery / 100);

        $imitation_market_order = $this->imitationMarketOrderSell($orderbook, $counting['market_discovery']['quote']['dirty'], $amount_precision, $price_precision);

        $counting['market_discovery']['amount'] = $imitation_market_order['base'];
        $counting['market_discovery']['price'] = $imitation_market_order['price'];
        $counting['market_discovery']['side'] = 'sell';

        $counting['exchange']['quote'] = $must_get_quote;
        $counting['exchange']['price'] = Math::incrementNumber($counting['market_discovery']['price'] * (1 - $fee_market_discovery / 100) * (1 - $fee_exchange / 100 - $profits['optimal'] / 100), $price_precision);

        $counting['exchange']['amount']['dirty'] = Math::incrementNumber($counting['exchange']['quote'] / $counting['exchange']['price'], $amount_precision, true);
        $counting['exchange']['amount']['clean'] = $counting['exchange']['amount']['dirty'] * (1 - $fee_exchange / 100);
        $counting['exchange']['side'] = 'buy';

        $counting['market_discovery']['confidence_interval']['price_max'] = $counting['exchange']['price'] / ((1 - $fee_market_discovery / 100) * (1 - $fee_exchange / 100 - $profits['max'] / 100));
        $counting['market_discovery']['confidence_interval']['price_min'] = $counting['exchange']['price'] / ((1 - $fee_market_discovery / 100) * (1 - $fee_exchange / 100 - $profits['min'] / 100));

        return $counting;
    }

    public function imitationMarketOrderBuy(array $orderbook, float $must_amount, float $price_precision): array
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

                break;
            }
        }

        return [
            'quote' => $quote,
            'price' => Math::incrementNumber($quote / $must_amount, $price_precision, true)
        ];
    }

    public function imitationMarketOrderSell(array $orderbook, float $must_quote, float $amount_precision, float $price_precision): array
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

                break;
            }
        }

        $base_final = Math::incrementNumber($base, $amount_precision, true);

        return [
            'base' => $base_final,
            'price' => Math::incrementNumber($must_quote / $base_final, $price_precision, true)
        ];
    }

    public function proofOpenOrdersAndMirrorOrders(): bool
    {
        $symbols = array_column($this->open_orders, 'symbol');

        if (count(array_unique($symbols)) < count($symbols))
            return false;

        return true;
    }

    public function proofBalances(float $delta = 1): bool
    {
        $sum_start = [];
        foreach ($this->start_balances as $start_balance) {
            foreach ($start_balance as $asset => $item) {
                if (isset($sum_start[$asset])) {
                    $sum_start[$asset] += $item['total'];
                } else {
                    $sum_start[$asset] = $item['total'];
                }
            }
        }

        foreach ($this->balances as $current_balance) {
            foreach ($current_balance as $asset => $item) {
                if (isset($sum_current[$asset])) {
                    $sum_current[$asset] += $item['total'];
                } else {
                    $sum_current[$asset] = $item['total'];
                }
            }
        }

        foreach ($sum_start as $asset => $sum)
            if (!isset($sum_current[$asset]) || ($sum_current[$asset] - $sum) / 100 <= -1 * $delta)
                return false;

        return true;
    }

    public function gwtStartBalances(): array
    {
        return $this->start_balances;
    }

    public function getBalances(): array
    {
        return $this->balances;
    }

    public function getOpenOrders(): array
    {
        return $this->open_orders;
    }

    public function getMirrorOrders(): array
    {
        return $this->mirror_orders;
    }

    public function getNeedUpdateBalanceExchange(): bool
    {
        return $this->need_update_balance_exchange;
    }

    public function getNeedUpdateBalanceMarketDiscovery(): bool
    {
        return $this->need_update_balance_market_discovery;
    }
}