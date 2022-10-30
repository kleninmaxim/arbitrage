<?php

namespace Src\Crypto;

use ccxt\Exchange;
use Exception;
use Src\Support\Log;

class Ccxt
{
    private Exchange $exchange;
    public string $name;
    public ?array $markets;
    public ?array $format_markets;

    public function __construct(Exchange $exchange)
    {
        $this->exchange = $exchange;
        $this->name = $exchange->id;
    }

    public static function init(string $exchange_name, bool $enableRateLimit = false, string $api_key = '', string $api_secret = '', array $ccxt_settings = []): static
    {
        $exchange_class = '\\ccxt\\' . $exchange_name;

        return new static(
            new $exchange_class(
                array_merge(
                    ['apiKey' => $api_key, 'secret' => $api_secret, 'enableRateLimit' => $enableRateLimit],
                    $ccxt_settings
                )
            )
        );
    }

    public function getOrderBook(string $symbol, int $depth = null): array|null
    {
        try {
            $orderbook = $this->exchange->fetch_order_book($symbol, $depth);
            $orderbook['exchange'] = $this->name;

            return $orderbook;
        } catch (Exception $e) {
            Log::error($e, ['$symbol' => $symbol, '$depth' => $depth]);
        }

        return null;
    }

    public function getOpenOrders(string $symbol = null): array|null
    {
        try {
            return $this->exchange->fetch_open_orders($symbol);
        } catch (Exception $e) {
            Log::error($e, ['$symbol' => $symbol]);
        }

        return null;
    }

    public function getOrderStatus(string $order_id, string $symbol = null): array|null
    {
        try {
            return $this->exchange->fetch_order_status($order_id, $symbol);
        } catch (Exception $e) {
            Log::error($e, ['$order_id' => $order_id, '$symbol' => $symbol]);
        }

        return null;
    }

    public function getBalances(array $assets = null): array|null
    {
        try {
            $balances = $this->exchange->fetch_balance();

            if ($assets) {
                foreach ($assets as $asset) {
                    if (!empty($balances[$asset])) {
                        $modify_balances[$asset] = $balances[$asset];
                    } else {
                        $modify_balances[$asset] = ['free' => 0, 'used' => 0, 'total' => 0];
                    }
                }

                return $modify_balances ?? [];
            }

            unset($balances['info']);
            unset($balances['timestamp']);
            unset($balances['datetime']);
            unset($balances['free']);
            unset($balances['used']);
            unset($balances['total']);

            return $balances;
        } catch (Exception $e) {
            Log::error($e, ['$assets' => $assets]);
        }

        return null;
    }

    public function createOrder(string $symbol, string $type, string $side, float $amount, float $price = null): array|null
    {
        try {
            return $this->exchange->create_order($symbol, $type, $side, $amount, $price);
        } catch (Exception $e) {
            Log::error($e, ['$symbol' => $symbol, '$type' => $type, '$side' => $side, '$amount' => $amount, '$price' => $price]);
        }

        return null;
    }

    public function cancelOrder(string $order_id, string $symbol = null): array|null
    {
        try {
            return $this->exchange->cancel_order($order_id, $symbol);
        } catch (Exception $e) {
            Log::error($e, ['$order_id' => $order_id, '$symbol' => $symbol]);
        }

        return null;
    }

    public function cancelAllOrder(string $symbol = null): array
    {
        if ($open_orders = $this->getOpenOrders($symbol))
            foreach ($open_orders as $open_order)
                $cancel_orders[] = $this->cancelOrder($open_order['id'], $open_order['symbol']);

        return $cancel_orders ?? [];
    }

    public function fetchMarkets(array $assets = [], bool $off_active = true): ?array
    {
        $markets = $this->exchange->fetch_markets();

        if ($assets)
            $markets = array_filter(
                $markets,
                fn($market) => in_array($market['base'], $assets) && in_array($market['quote'], $assets) && ($market['base'] . '/' . $market['quote'] == $market['symbol']) && (!$off_active || $market['active'])
            );

        return $this->markets = $markets;
    }

    /**
     * @throws Exception
     */
    public function getMarkets(array $assets = [], bool $active = true): array
    {
        if (empty($this->markets))
            $this->fetchMarkets($assets, $active);

        foreach ($this->markets as $market) {
            $symbol = $market['symbol'];

            if (empty($market['precision']['price']) || empty($market['precision']['amount'])) {
                $precisions = $this->getPrecisionsByOrderBook($symbol);

                $market['precision']['price'] = $market['precision']['price'] ?? $this->formatIncrement($precisions['price']);
                $market['precision']['amount'] = $market['precision']['amount'] ?? $this->formatIncrement($precisions['amount']);
            }

            $format_markets[$symbol] = [
                'id' => $market['id'],
                'price_increment' => $this->formatIncrement($market['precision']['price']),
                'amount_increment' => $this->formatIncrement($market['precision']['amount'])
            ];
        }

        return $this->format_markets = $format_markets ?? [];
    }

    /**
     * @throws Exception
     */
    private function getPrecisionsByOrderBook(string $symbol): array
    {
        $orderbook = $this->getOrderBook($symbol);

        if (!empty($orderbook['bids']) && !empty($orderbook['asks'])) {
            foreach (array_merge($orderbook['bids'], $orderbook['asks']) as $dom)
                foreach (['0' => 'price', '1' => 'amount'] as $key => $item)
                    $precisions[$item][] = strlen(substr(strrchr($dom[$key], '.'), 1));

            if (!empty($precisions))
                return [
                    'price' => max($precisions['price']),
                    'amount' => max($precisions['amount'])
                ];
        }

        throw new Exception('probably orderbook bid and ask is empty and not found precisions');
    }

    private function formatIncrement(int|float $precision): float|int
    {
        return (is_int($precision))
            ? number_format(10 ** (-1 * $precision), $precision)
            : $precision;
    }

    public function getExchange(): Exchange
    {
        return $this->exchange;
    }
}