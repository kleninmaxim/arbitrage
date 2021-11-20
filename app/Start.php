<?php

namespace app;

class Start
{

    public static function pmStartOrderBook($pair)
    {

        $command = 'pm2 start ' . dirname(__DIR__) . '/pm2/orderbook/example.php --name "[' .
            EXCHANGE .
            '] ' .
            $pair .
            '"  --namespace "orderbook" -o /dev/null -e /dev/null -m -f -- "' .
            $pair .
            '" ';

        exec($command, $result, $code);

        if ($code === 0) echo '[OK] ' . $pair . ' orderbook started' . PHP_EOL;
        else die();

    }

    public static function pmStartTriangles($triangle)
    {

        $assets = \src\Act::getAssets($triangle);

        $command = 'pm2 start ' . dirname(__DIR__) . '/pm2/triangles/example.php --name "[' .
            EXCHANGE . '] ' . $assets[0] . '|' . $assets[1] . '|' . $assets[2] .
            '"  --namespace "triangle" -o /dev/null -e /dev/null -m -f -- "' .
            $triangle[0] . '" "' . $triangle[1] . '" "' . $triangle[2] .
            '" ';

        exec($command, $result, $code);

        if ($code === 0) echo '[OK] ' . $assets[0] . '|' . $assets[1] . '|' . $assets[2] .' triangle started' . PHP_EOL;
        else die();

    }

    public static function getPairsByTriangles($triangles)
    {

        $all = [];

        foreach ($triangles as $triangle)
            foreach ($triangle as $tr)
                $all[] = $tr;

        return array_values(array_unique($all));

    }

    public static function getTriangles($pairs)
    {

        $triangles = [];

        foreach ($pairs as $pair_one) {

            list($base_one, $quote_one) = explode('/', $pair_one);

            foreach ($pairs as $pair_two) {

                list($base_two, $quote_two) = explode('/', $pair_two);

                if ($pair_two != $pair_one) {

                    if ($base_two == $base_one)
                        self::addTriangle($triangles, $quote_one, $quote_two, $pairs, $pair_one, $pair_two);
                    elseif ($base_two == $quote_one)
                        self::addTriangle($triangles, $base_one, $quote_two, $pairs, $pair_one, $pair_two);
                    elseif ($quote_two == $base_one)
                        self::addTriangle($triangles, $base_two, $quote_one, $pairs, $pair_one, $pair_two);
                    elseif ($quote_two == $quote_one)
                        self::addTriangle($triangles, $base_one, $base_two, $pairs, $pair_one, $pair_two);

                }

            }

        }

        return $triangles;

    }

    private static function addTriangle(&$triangles, $base, $quote, $pairs, $pair_one, $pair_two)
    {

        $pair_three_one = $base . '/' . $quote;

        $pair_three_two = $quote . '/' . $base;

        if (in_array($pair_three_one, $pairs))
            self::processTriangle($triangles, $pair_one, $pair_two, $pair_three_one);
        elseif (in_array($pair_three_two, $pairs))
            self::processTriangle($triangles, $pair_one, $pair_two, $pair_three_two);

    }

    private static function processTriangle(&$triangles, $pair_one, $pair_two, $pair_three)
    {

        list($base_one, $quote_one) = explode('/', $pair_one);

        list($base_two, $quote_two) = explode('/', $pair_two);

        list($base_three, $quote_three) = explode('/', $pair_three);

        $courses = array_keys(COURSES);

        if (
            in_array($base_one, $courses) ||
            in_array($quote_one, $courses) ||
            in_array($base_two, $courses) ||
            in_array($quote_two, $courses) ||
            in_array($base_three, $courses) ||
            in_array($quote_three, $courses)
        ) {

            foreach ($triangles as $triangle) {

                if (
                    in_array($pair_one, $triangle) &&
                    in_array($pair_two, $triangle) &&
                    in_array($pair_three, $triangle)
                ) {

                    $exist = true;

                    break;
                }

            }

            if (!isset($exist)) $triangles[] = [$pair_one, $pair_two, $pair_three];

        }

    }

}