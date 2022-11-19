<?php

namespace Src\Crypto\Exchanges\Original;

class Exmo
{
    private string $name = 'exmo';
    private string $websocket_connection = 'wss://ws-api.exmo.com:443/v1/public';
    private array $stream_names = [
        'order_book_snapshots' => 'spot/order_book_snapshots:'
    ];
}