<?php

namespace src;

class Rate
{

    public static function getGlassRates($orderbooks)
    {

        foreach (array_keys($orderbooks) as $pair) {

            $rates[$pair] = round(
                ($orderbooks[$pair]['bids'][0][0] + $orderbooks[$pair]['asks'][0][0]) / 2,
                $orderbooks[$pair]['price_precision']
            );

        }

        return self::getRates($rates ?? []);

    }

    public static function getRates($rates)
    {

        if (!empty($rates)) {

            $rate_courses = [];

            foreach (ASSETS as $asset) {

                foreach (array_keys(COURSES) as $course) {

                    $pair = $asset . '/' . $course;

                    if ($asset != $course) {

                        if (isset($rates[$pair])) $rate_courses[$pair] = $rates[$pair];
                        elseif (isset($rates[$course . '/' . $asset])) $rate_courses[$pair] = 1 / $rates[$course . '/' . $asset];
                        else {

                            self::mergeAsset($rate_courses, $rates, $asset, $course, $pair);

                        }

                    } else {
                        $rate_courses[$pair] = 1;
                    }

                }

            }

/*            $zero_courses = array_filter($rate_courses, function ($v) {
                return $v == 0;
            }, ARRAY_FILTER_USE_BOTH);

            foreach ($zero_courses as $key_p => $zero_course) {

                $delete = COURSE;
                $delete[] = '/';

                $asset = str_replace($delete, '', $key_p);
                $course = str_replace(['/', $asset], '', $key_p);

                self::mergeAsset($rate_courses, $rate_courses, $asset, $course, $key_p);

            }*/

            foreach ($rate_courses as $key => $rate_course) {

                list($asset, $course) = explode('/', $key);

                $final[$asset][$course] = $rate_course;

            }

        }

        return $final ?? [];

    }

    private static function mergeAsset(&$rate_courses, $rates, $asset, $course, $pair)
    {

        $common_pairs = array_filter($rates, function ($v, $k) use ($asset) {
            return is_int(strpos($k, $asset)) && $v != 0;
        }, ARRAY_FILTER_USE_BOTH);

        if (!empty($common_pairs)) {

            foreach ($common_pairs as $key => $common_pair) {

                $k1 = 0;
                $k2 = 0;

                $find_asset = str_replace(['/', $asset], '', $key);

                if (isset($rates[$course . '/' . $find_asset]) && $rates[$course . '/' . $find_asset] != 0) {

                    $k1 = $common_pair;

                    if (isset($common_pairs[$asset . '/' . $find_asset])) $k2 = 1 / $rates[$course . '/' . $find_asset];
                    elseif (isset($common_pairs[$find_asset . '/' . $asset])) $k2 = $rates[$course . '/' . $find_asset];

                } elseif (isset($rates[$find_asset . '/' . $course]) && $rates[$find_asset . '/' . $course] != 0) {

                    $k1 = 1 / $common_pair;

                    if (isset($common_pairs[$asset . '/' . $find_asset])) $k2 = 1 / $rates[$find_asset . '/' . $course];
                    elseif (isset($common_pairs[$find_asset . '/' . $asset])) $k2 = $rates[$find_asset . '/' . $course];

                }

                if ($k1 != 0 && $k2 != 0) {

                    $rate_courses[$pair] = $k1 * $k2;

                    break;

                }

            }

            $rate_courses[$pair] = $rate_courses[$pair] ?? 0;

        } else $rate_courses[$pair] = 0;
        
    }
    
}