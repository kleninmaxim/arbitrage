<?php

namespace app;

use src\Ccxt;
use src\DB;
use WebSocket\Client;

class Websocket
{

    private static $client;
    private static $pair;

    public static function connect($pair)
    {

        self::$client = new Client(
            'wss://stream.binance.com:9443/ws/' . Ccxt::reformatPair($pair) . '@depth' . DEPTH,
            ['timeout' => TIMEOUT]
        );

        self::$pair = $pair;

    }

    public static function run()
    {

        if (is_null(self::$client)) return false;
        if (is_null(self::$pair)) return false;

        try {

            DB::updateOrderBook(
                Ccxt::changeOrderbook(
                    json_decode(self::$client->receive(), true),
                    self::$pair
                ),
                self::$pair
            );

            return true;

        } catch (\WebSocket\ConnectionException $e) {

            DB::insertError(
                'Can get orderbook for pair ' . self::$pair,
                json_encode($e->getMessage())
            );

            return false;

        }
        
    }

    public static function close()
    {

        if (!is_null(self::$client)) self::$client->close();


    }
    
}