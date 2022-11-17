<?php

namespace Src\Databases\MySqlTables;

trait BalancesTable
{
    public function replaceBalances(string $exchange, string $asset, array $balance): static
    {
        return $this->replace(
            'balances',
            [
                'exchange_id' => $this->getParentId('exchanges', 'exchange', $exchange),
                'asset' => $asset,
                'free' => $balance['free'],
                'used' => $balance['used'],
                'total' => $balance['total']
            ]
        );
    }
}