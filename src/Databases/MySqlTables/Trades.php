<?php

namespace Src\Databases\MySqlTables;

trait Trades
{
    public function insertTrades(
        string $exchange,
        int $trade_id,
        int $order_id,
        string $symbol,
        string $trade_type,
        string $side,
        float $price,
        float $amount,
        float $quote,
        string $fee_asset,
        float $fee_amount,
        float $timestamp,
        string $datetime
    ): static
    {
        return $this->insert(
            'trades',
            [
                'exchange_id' => $this->getParentId('exchanges', 'exchange', $exchange),
                'trade_id' => $trade_id,
                'order_id' => $order_id,
                'symbol' => $symbol,
                'trade_type' => $trade_type,
                'side' => $side,
                'price' => $price,
                'amount' => $amount,
                'quote' => $quote,
                'fee_asset' => $fee_asset,
                'fee_amount' => $fee_amount,
                'timestamp' => $timestamp,
                'datetime' => $datetime,
            ]
        );
    }
}