<?php

namespace src;

class Orderbook
{

    private static float $reserve = 1.05;

    public static function simplified(&$orderbooks, $rates)
    {

        $pairs = array_keys($orderbooks);

        foreach ($orderbooks as $pair => $orderbook) {

            list($base_asset, $quote_asset) = explode('/', $pair);

            $courses = array_keys(COURSES);

            $deal_ticker = '';

            if (in_array($quote_asset, $courses)) $deal_ticker = $quote_asset;
            elseif (in_array($base_asset, $courses)) $deal_ticker = $base_asset;
            else {

                foreach (COURSES as $course => $deal_amount)
                    if (in_array($base_asset . '/' . $course, $pairs))
                        $deal_ticker = $course;

                if (empty($deal_ticker)) throw new \Exception();

            }

            $amount = round(COURSES[$deal_ticker] / $rates[$base_asset][$deal_ticker], $orderbook['amount_precision']);

            $amounts[$pair] = $amount;

            $bids = 0;

            foreach ($orderbook['bids'] as $bid) {

                $bids += $bid[1];

                $orderbooks[$pair]['bids'] = ['price' => $bid[0]];

                if ($bids > $amount * self::$reserve) break;

            }

            $asks = 0;

            foreach ($orderbook['asks'] as $ask) {

                $asks += $ask[1];

                $orderbooks[$pair]['asks'] = ['price' => $ask[0]];

                if ($asks > $amount * self::$reserve) break;

            }

        }

        return $amounts ?? [];

    }

}