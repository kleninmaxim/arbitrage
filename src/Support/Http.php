<?php

namespace Src\Support;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class Http
{
    private static Client $client;

    public static function get(string $url, array $query = [], array $headers = [], bool $json = true)
    {
        return self::request('GET', $url, $query, $headers, $json);
    }

    public static function post(string $url, array $query = [], array $headers = [], bool $json = true)
    {
        return self::request('POST', $url, $query, $headers, $json);
    }

    private static function request(string $method, string $url, array $query = [], array $headers = [], bool $json = true)
    {
        if (!isset(self::$client))
            self::$client = new Client(['timeout' => 10]);

        try {
            $response = self::$client->request(
                $method,
                $url,
                [
                    'headers' => $headers,
                    'query' => $query
                ]
            );
        } catch (GuzzleException $e) {
            Log::error($e, ['$method' => $method, '$url' => $url, '$query' => $query, '$headers' => $headers]);
            return false;
        }

        if ($json)
            return json_decode($response->getBody(), true);

        return $response->getBody()->getContents();
    }
}