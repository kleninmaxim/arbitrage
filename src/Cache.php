<?php

namespace src;

class Cache
{
    
    public static function createFiles()
    {

        $files = [
            dirname(__DIR__) . '/cache/pairs.json',
            dirname(__DIR__) . '/cache/triangles.json',
            dirname(__DIR__) . '/cache/precisions.json'
        ];

        foreach ($files as $file) {

            if (!file_exists($file)) {

                $fp = fopen($file, 'w');

                fwrite($fp, '');

                fclose($fp);

            }

        }
        
    }

    public static function putPairs($pairs)
    {

        file_put_contents(dirname(__DIR__) . '/cache/pairs.json', json_encode($pairs));

    }

    public static function getPairs()
    {

        return json_decode(file_get_contents(dirname(__DIR__) . '/cache/pairs.json'), true);

    }

    public static function putTriangles($triangles)
    {

        file_put_contents(dirname(__DIR__) . '/cache/triangles.json', json_encode($triangles));

    }

    public static function getTriangles()
    {

        return json_decode(file_get_contents(dirname(__DIR__) . '/cache/triangles.json'), true);

    }

    public static function putPrecisions($precisions)
    {

        file_put_contents(dirname(__DIR__) . '/cache/precisions.json', json_encode($precisions));

    }

    public static function getPrecisions()
    {

        return json_decode(file_get_contents(dirname(__DIR__) . '/cache/precisions.json'), true);

    }

}