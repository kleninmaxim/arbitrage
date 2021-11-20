<?php

namespace src;

class Result
{

    public static function get($act)
    {

        $pairs = array_merge(
            array_column($act['t'], 'pair'),
            array_column($act, 'pair')
        );

        $one = self::createStepMaker($pairs, $act['m']);

        $two = self::createStep($pairs, $act['t'], $one);

        $three = self::createStep($pairs, $act['t'], $two);

        return [
            'one' => $one,
            'two' => $two,
            'three' => $three,
        ];

    }

    public static function countProfit($result)
    {

        $profit = [];

        $pairs = array_unique(
            array_merge(
                array_column($result, 'base_asset'),
                array_column($result, 'quote_asset')
            )
        );

        foreach ($pairs as $pair) $profit[$pair] = 0;

        foreach ($result as $r) {

            $profit[$r['base_asset']] += $r['base_asset_amount'];
            $profit[$r['quote_asset']] += $r['quote_asset_amount'];

        }

        return $profit;

    }

    private static function createStepMaker(&$pairs, $maker)
    {

        list($base_asset, $quote_asset) = explode('/', $maker['pair']);

        $amount = ($maker['side'] == 'buy') ? $maker['amount'] : -1 * $maker['amount'];

        self::unsetPair($pairs, $maker['pair']);

        return [
            'base_asset' => $base_asset,
            'base_asset_amount' => $amount,
            'quote_asset' => $quote_asset,
            'quote_asset_amount' => -1 * $amount * $maker['price'],
            'price' => $maker['price'],
            'side' => $maker['side'],
        ];

    }

    private static function createStep(&$pairs, $takers, $step)
    {

        [$asset, $amount] = self::getAssetAndAmount($step);

        $pair = self::findPair($pairs, $asset);

        list($base_asset, $quote_asset) = explode('/', $pair);

        [$base_asset_amount, $quote_asset_amount] = self::baseAndQuoteAssetsAmount($asset, $base_asset, $amount, $takers[$pair]['price']);

        self::unsetPair($pairs, $pair);

        return [
            'base_asset' => $base_asset,
            'base_asset_amount' => Math::floor($base_asset_amount, $takers[$pair]['amount_precision']),
            'quote_asset' => $quote_asset,
            'quote_asset_amount' => $quote_asset_amount,
            'price' => $takers[$pair]['price'],
            'side' => $takers[$pair]['side'],
        ];

    }

    private static function unsetPair(&$pairs, $pair)
    {

        unset($pairs[array_search($pair, $pairs)]);

    }

    private static function findPair($pairs, $asset)
    {

        foreach ($pairs as $pair) if (strpos($pair, $asset) !== false) return $pair;

        throw new Exception();

    }

    private static function getAssetAndAmount($step)
    {

        $condition_side = ($step['side'] == 'buy');

        return [
            $condition_side ? $step['base_asset'] : $step['quote_asset'],
            $condition_side ? $step['base_asset_amount'] : $step['quote_asset_amount']
        ];

    }

    private static function baseAndQuoteAssetsAmount($asset, $base_asset, $amount, $price)
    {

        if ($asset == $base_asset) {
            $base_asset_amount = -1 * $amount;
            $quote_asset_amount = -1 * $base_asset_amount * $price;
        } else {
            $quote_asset_amount = -1 * $amount;
            $base_asset_amount = -1 * $quote_asset_amount / $price;
        }

        return [$base_asset_amount, $quote_asset_amount];

    }

}