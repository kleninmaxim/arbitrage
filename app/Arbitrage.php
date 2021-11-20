<?php

namespace app;

use src\Act;
use src\DB;
use src\Orderbook;
use src\PriceMaker;
use src\Rate;

class Arbitrage
{

    public static function run($pairs, $position)
    {
        
        $orderbooks = self::getOrderbooks($pairs);

        if (!empty($orderbooks)) {

            $amounts = Orderbook::simplified(
                $orderbooks,
                Rate::getGlassRates($orderbooks)
            );

            $acts = Act::get(
                $position,
                $orderbooks,
                $amounts
            );

            return self::countStrategy($acts);

        }

        return false;

    }

    private static function countStrategy($acts)
    {

        foreach ($acts as $act) {

            $profit_percentage = PriceMaker::countProfit($act);

            if ($profit_percentage >= PROFIT) {

                [$result, $profit] = Act::work($act);

                DB::insertTheoreticalOrders(
                    1,
                    2,
                    3,
                    Act::getTriangles($act),
                    $profit_percentage,
                    json_encode($result),
                    json_encode($profit)
                );

            }

            $profit_percentages[] = $profit_percentage;

        }

        return $profit_percentages ?? false;

    }

    private static function getOrderbooks($pairs)
    {

        foreach ($pairs as $pair) {

            $orderbook = DB::selectOrderbook($pair);

            if (abs(strtotime(date('d-m-Y H:i:s')) - strtotime($orderbook['updated_at'])) <= 5) {

                $orderbooks[$pair] = json_decode(
                    $orderbook['orderbook'],
                    true
                );

            }

            if (empty($orderbooks[$pair])) return [];

        }

        return $orderbooks ?? [];
        
    }

}