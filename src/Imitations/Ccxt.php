<?php

namespace Src\Imitations;

class Ccxt
{
    public string $exchange;
    public string $name;
    public ?array $markets;
    public ?array $format_markets;

    public function __construct($exchange)
    {
        $this->exchange = $exchange;
        $this->name = $exchange;
    }

    public static function init(string $exchange_name): static
    {
        return new static($exchange_name);
    }

    public function getOpenOrders(string $symbol = null): array
    {
        echo '[' . date('Y-m-d H:i:s') . '] Get Open Orders: ' . $this->name . PHP_EOL;
        return [];
    }

    public function getOrderStatus(string $order_id, string $symbol = null): array
    {
        echo '[' . date('Y-m-d H:i:s') . '] Get Order Statuses: ' . $this->name . PHP_EOL;
        return [];
    }

    public function cancelAllOrder(): array
    {
        echo '[' . date('Y-m-d H:i:s') . '] Cancel All orders: ' . $this->name . PHP_EOL;
        return [];
    }

    public function cancelOrder(string $order_id, string $symbol = null): array
    {
        echo '[' . date('Y-m-d H:i:s') . '] Cancel Order: ' . $this->name . PHP_EOL;
        return [];
    }

    public function createOrder(string $symbol, string $type, string $side, float $amount, float $price = null): array
    {
        echo '[' . date('Y-m-d H:i:s') . '] Create Order: ' . $symbol . ' ' . $type . ' ' . $side . ' ' . $amount . ' ' . $price . PHP_EOL;
        return [];
    }

    public function getBalances(array $assets = null): array
    {
        if ($this->exchange == 'exmo')
            return [
                'BTC' => ['free' => 0.5, 'used' => 0, 'total' => 0.5],
                'ETH' => ['free' => 3, 'used' => 0, 'total' => 3],
                'USDT' => ['free' => 10000, 'used' => 0, 'total' => 10000]
            ];

        return [
            'BTC' => ['free' => 0.6, 'used' => 0, 'total' => 0.6],
            'ETH' => ['free' => 4, 'used' => 0, 'total' => 4],
            'USDT' => ['free' => 10100, 'used' => 0, 'total' => 10100]
        ];
    }
}