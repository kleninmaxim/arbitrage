<?php

namespace src;

class PriceMaker
{

    public static function countProfit($act)
    {

        $price_taker = 1;

        foreach ($act['t'] as $taker)
            $price_taker *= ($taker['side'] == 'buy') ? 1 / $taker['price'] : $taker['price'];

        if ($act['m']['side'] == 'buy')
            return Math::floor(
                ($price_taker / $act['m']['price'] - 1) * 100,
                2
            );

        if ($act['m']['side'] == 'sell')
            return Math::floor(
                ($act['m']['price'] * $price_taker - 1) * 100,
                2
            );

        throw new \Exception();

    }

    public static function bidAsk($side) {

        if ($side == 'buy') return 'asks';

        if ($side == 'sell') return 'bids';

        return '';

    }

}