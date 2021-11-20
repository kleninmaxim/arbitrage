<?php

namespace src;

class Ccxt
{

    private static $exchange;
    private static $markets;

    public static function createExchange($enableRateLimit = true)
    {

        try {

            $exchange_class = '\\ccxt\\' . EXCHANGE;

            self::$exchange = new $exchange_class(
                ['apiKey' => '', 'secret' => '', 'timeout' => 10000, 'enableRateLimit' => $enableRateLimit]
            );

            self::$exchange->load_markets();

        } catch (\ccxt\NetworkError $e) {

            \src\DB::insertError(
                'Can create Exchange NetworkError',
                json_encode($e->getMessage())
            );

            throw new \Exception();

        } catch (\ccxt\ExchangeError $e) {

            \src\DB::insertError(
                'Can create Exchange ExchangeError',
                json_encode($e->getMessage())
            );

            throw new \Exception();

        } catch (\Exception $e) {

            \src\DB::insertError(
                'Can create Exchange Exception',
                json_encode($e->getMessage())
            );

            throw new \Exception();

        }

    }

    public static function fetchMarkets()
    {

        if ((is_null(self::$exchange))) self::createExchange();

        try {

            self::$markets = self::$exchange->fetch_markets();

        } catch (\Exception $e) {

            \src\DB::insertError(
                'Can get fetch markets',
                json_encode($e->getMessage())
            );

            throw new \Exception();

        }

    }

    public static function changeOrderbook($orderbook, $pair)
    {

        [$amount_precision, $price_precision] = self::addPrecisions($orderbook, $pair);

        return [
            'bids' => $orderbook['bids'],
            'asks' => $orderbook['asks'],
            'amount_precision' => $amount_precision,
            'price_precision' => $price_precision,
        ];

    }

    public static function reformatPair($pair)
    {

        return mb_strtolower(str_replace('/', '', $pair));

    }

    public static function getMarkets()
    {
        if ((is_null(self::$exchange))) self::fetchMarkets();

        foreach (self::$markets as $market) {

            if (
                in_array($market['base'], ASSETS) && in_array($market['quote'], ASSETS)
            ) {
                
                $pairs[] = $market['symbol'];
                
            }

        }

        return $pairs ?? [];

    }

    private static function addPrecisions($orderbook, $pair)
    {

        $precisions = Cache::getPrecisions();

        if (empty($precisions) || !in_array($pair, array_keys($precisions))) {

            print_r('I here'); echo PHP_EOL;

            if ((is_null(self::$exchange))) self::createExchange();
            if ((is_null(self::$markets))) self::fetchMarkets();

            if (in_array($pair, array_keys(self::$exchange->markets))) {

                $info = self::$markets[
                array_search(
                    $pair,
                    array_column(self::$markets, 'symbol')
                )
                ];

                if (
                    !isset($info['precision']['amount']) ||
                    !isset($info['precision']['price']) ||
                    is_null($info['precision']['amount']) ||
                    is_null($info['precision']['price'])
                ) {

                    foreach (array_merge($orderbook['bids'], $orderbook['asks']) as $dom) {

                        $amount_precision[] = strlen(substr(strrchr($dom['1'], '.'), 1));

                        $price_precision[] = strlen(substr(strrchr($dom['0'], '.'), 1));

                    }

                    $amount_precision = isset($amount_precision) ? max($amount_precision) : die();
                    $price_precision = isset($price_precision) ? max($price_precision) : die();

                    $precisions[$pair] = [
                        'amount_precision' => $amount_precision,
                        'price_precision' => $price_precision,
                    ];

                    Cache::putPrecisions($precisions);

                    return [$amount_precision, $price_precision];

                } else {

                    $precisions[$pair] = [
                        'amount_precision' => $info['precision']['amount'],
                        'price_precision' => $info['precision']['price'],
                    ];

                    Cache::putPrecisions($precisions);

                    return [$info['precision']['amount'], $info['precision']['price']];
                }

            }

            throw new \Exception();

        }

        return [$precisions[$pair]['amount_precision'], $precisions[$pair]['price_precision']];

    }

}