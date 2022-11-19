<?php

namespace Src\Standards;

class WebsocketDataStandard
{
    public static function orderbook(string $symbol, array $bids, array $asks, mixed $timestamp, mixed $datetime, mixed $nonce, string $exchange): array
    {
        return self::response('isOrderbook', [
            'symbol' => $symbol,
            'bids' => $bids,
            'asks' => $asks,
            'timestamp' => $timestamp,
            'datetime' => $datetime,
            'nonce' => $nonce,
            'exchange' => $exchange
        ]);
    }

    public static function error(): array
    {
        return self::response('error');
    }

    public static function original(string $response, mixed $data = null): array
    {
        return self::response($response, $data);
    }

    private static function response(string $response, mixed $data = null): array
    {
        return ['response' => $response, 'data' => $data];
    }
}