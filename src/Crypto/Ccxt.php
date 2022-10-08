<?php

namespace Src\Crypto;

use ccxt\Exchange;
use Exception;

class Ccxt
{
    private Exchange $exchange;
    public ?array $markets;
    public ?array $format_markets;

    public function __construct(Exchange $exchange)
    {
        $this->exchange = $exchange;
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

    /**
     * @throws Exception
     */
    public function getOrderBook(string $symbol, int $depth = null): array
    {
        return $this->exchange->fetch_order_book($symbol, $depth);
    }

    /**
     * @throws Exception
     */
    public function getOpenOrders(string $symbol = null): array
    {
        return $this->exchange->fetch_open_orders($symbol);
    }

    /**
     * @throws Exception
     */
    public function getBalances(array $assets = null): array
    {
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

        return $balances;
    }

    /**
     * @throws Exception
     */
    public function createOrder(string $symbol, string $type, string $side, float $amount, float $price = null): array
    {
        return $this->exchange->create_order($symbol, $type, $side, $amount, $price);
    }

    /**
     * @throws Exception
     */
    public function cancelOrder(string $order_id, string $symbol = null): array
    {
        return $this->exchange->cancel_order($order_id, $symbol);
    }

    /**
     * @throws Exception
     */
    public function cancelAllOrder(string $symbol = null): array
    {
        if ($open_orders = $this->getOpenOrders($symbol))
            foreach ($open_orders as $open_order)
                $cancel_orders[] = $this->exchange->cancel_order($open_order['id'], $open_order['symbol']);

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
        if (!isset($this->markets))
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