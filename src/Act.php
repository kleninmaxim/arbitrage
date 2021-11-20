<?php

namespace src;

class Act
{

    public static function get($actions, $orderbooks, $amounts) {

        $acts = [];

        foreach ($actions as $key => $action) {

            $maker = array_key_first($action);

            $side = $action[$maker];

            list($base_asset_maker, $quote_asset_maker) = explode('/', $maker);

            foreach (array_keys($orderbooks) as $pair) {

                if ($pair != $maker) {

                    list($base_asset, $quote_asset) = explode('/', $pair);

                    if ($base_asset == $quote_asset_maker || $quote_asset == $base_asset_maker) {
                        $acts[$key]['t'][$pair] = [
                            'pair' => $pair,
                            'side' => $side,
                            'price' => $orderbooks[$pair][PriceMaker::bidAsk($side)]['price'],
                            'amount_precision' => $orderbooks[$pair]['amount_precision'],
                            'price_precision' => $orderbooks[$pair]['price_precision'],
                        ];

                    } elseif ($quote_asset == $quote_asset_maker || $base_asset == $base_asset_maker)
                        $acts[$key]['t'][$pair] = [
                            'pair' => $pair,
                            'side' => self::changeSide($side),
                            'price' => $orderbooks[$pair][PriceMaker::bidAsk(self::changeSide($side))]['price'],
                            'amount_precision' => $orderbooks[$pair]['amount_precision'],
                            'price_precision' => $orderbooks[$pair]['price_precision'],

                        ];

                } else {

                    $acts[$key]['m'] = [
                        'pair' => $pair,
                        'side' => $side,
                        'amount' => $amounts[$pair],
                        'price' => $orderbooks[$pair][PriceMaker::bidAsk($side)]['price'],
                        'amount_precision' => $orderbooks[$pair]['amount_precision'],
                        'price_precision' => $orderbooks[$pair]['price_precision']
                    ];

                }

            }

        }

        return $acts;

    }

    public static function work($act) {

        $result = Result::get($act);

        return [
            $result,
            Result::countProfit($result),
        ];

    }

    public static function getTriangles($act)
    {

        $triangles = '|';

        foreach (self::getAssets(self::getPairs($act)) as $d) $triangles .= $d . '|';

        return $triangles;

    }

    public static function getAssets($pairs)
    {

        foreach ($pairs as $pair) {

            list($base, $quote) = explode('/', $pair);
            $del[] = $base;
            $del[] = $quote;

        }

        return array_values(array_unique($del));

    }

    public static function getPairs($act)
    {

        return array_merge(
            array_column($act['t'], 'pair'),
            array_column($act, 'pair')
        );

    }

    private static function changeSide($side) {

        return ($side == 'sell') ? 'buy' : 'sell';

    }

}