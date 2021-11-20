<?php

namespace src;

class Math
{

    public static function floor($float, $precision)
    {

        return intval($float * 10 ** $precision) / 10 ** $precision;

    }

}