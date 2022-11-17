<?php

namespace Src\Databases\MySqlTables;

trait OrdersTable
{
    public function insertOrUpdateOrders(
        string $exchange,
        string $order_id,
        string $symbol,
        string $side,
        float $price,
        float $amount,
        float $quote,
        string $status,
        float $filled,
        float $timestamp,
        string $datetime,
    ): static
    {
        return $this->insertOrUpdate(
            'orders' ,
            [
                'order_id' => $order_id,
                'exchange_id' => $this->getParentId('exchanges', 'exchange', $exchange),
                'symbol' => $symbol,
                'side' => $side,
                'price' => $price,
                'amount' => $amount,
                'quote' => $quote,
                'status' => $status,
                'filled' => $filled,
                'timestamp' => $timestamp,
                'datetime' => $datetime
            ],
            ['symbol', 'side', 'price', 'amount', 'quote', 'status', 'filled', 'timestamp', 'datetime']
        );
    }
}