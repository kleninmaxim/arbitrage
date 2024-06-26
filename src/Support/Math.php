<?php

namespace Src\Support;

class Math
{
    public static function compareFloats(float $a, float $b, int $decimals = 8): bool
    {
        $int_a = intval($a);
        $int_b = intval($b);

        if (
            $int_a == $int_b &&
            bccomp(
                number_format($a - $int_a, $decimals),
                number_format($b - $int_b, $decimals),
                $decimals
            ) == 0
        ) return true;

        return false;
    }

    public static function incrementNumber(float $number, float $increment, bool $round_up = false): float
    {
        if ($round_up)
            $number += $increment;

        return $increment * floor($number / $increment);
    }
}