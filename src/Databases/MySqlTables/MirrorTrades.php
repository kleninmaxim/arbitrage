<?php

namespace Src\Databases\MySqlTables;

trait MirrorTrades
{
    public function insertMirrorTrades(int $trade_id, int $order_id): static
    {
        return $this->insert('mirror_trades', ['trade_id' => $trade_id, 'order_id' => $order_id]);
    }
}