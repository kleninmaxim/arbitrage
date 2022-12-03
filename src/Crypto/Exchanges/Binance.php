<?php

namespace Src\Crypto\Exchanges;

use Src\Support\Http;
use Src\Support\Log;

class Binance
{
    protected string $name = 'binance';
    protected string $base_url = 'https://api.binance.com';
    protected string $public_api;
    protected string $private_api;

    public function __construct(string $public_api, string $private_api)
    {
        $this->public_api = $public_api;
        $this->private_api = $private_api;
    }

    public static function init(...$parameters): static
    {
        return new static(...$parameters);
    }

    public function getOrderbook(
        string $symbol,  // BTCUSDT
        int $depth = 100 // under 5000
    ): ?array
    {
        $response = $this->sendPublicRequest('get', '/api/v3/depth', ['symbol' => $symbol, 'limit' => $depth]);
        if (!empty($response['lastUpdateId']))
            return $response;
        return null;
    }

    public function getBalances(array $assets = []): ?array
    {
        $response = $this->sendPrivateRequest('get', '/api/v3/account');

        if (!empty($response['balances'])) {
            foreach ($response['balances'] as $balance)
                $balances[$balance['asset']] = ['free' => $balance['free'], 'used' => $balance['locked'], 'total' => round($balance['free'] + $balance['locked'], 8)];

            if ($assets) {
                foreach ($assets as $asset)
                    $modify_balances[$asset] = (!empty($balances[$asset])) ? $balances[$asset] : ['free' => 0, 'used' => 0, 'total' => 0];

                return $modify_balances ?? [];
            }

            return $balances ?? [];
        }

        Log::warning(['file' => __FILE__, '$response' => $response]);
        return null;
    }

    public function createOrder(
        string $symbol, // BTC/USDT
        string $type,   // MARKET
        string $side,   // BUY SELL
        float $amount
    ): ?array
    {
        return $this->sendPrivateRequest(
            'post',
            '/api/v3/order',
            ['symbol' => str_replace('/', '', $symbol), 'type' => $type, 'side' => $side, 'quantity' => $amount]
        );
    }

    protected function request(string $method, string $url, array $query = [], array $header = []): array
    {
        return Http::$method($url, $query, $header) ?: [];
    }

    private function sendPublicRequest(string $method, string $url, array $get_params = []): array
    {
        ksort($get_params);
        return $this->request($method, $this->base_url . $url, $get_params);
    }

    private function sendPrivateRequest(string $method, string $url, array $get_params = []): array
    {
        $get_params['timestamp'] = $this->getTimestamp();
        ksort($get_params);
        return $this->request(
            $method,
            $this->base_url . $url,
            array_merge($get_params, ['signature' => $this->generateSignature($get_params)]),
            ['X-MBX-APIKEY' => $this->public_api, 'Content-Type' => 'application/x-www-form-urlencoded']
        );
    }

    protected function generateSignature($query): string
    {
        return hash_hmac('sha256', http_build_query($query), $this->private_api);
    }

    protected function getTimestamp(): string
    {
        list($msec, $sec) = explode(' ', microtime());
        return $sec . substr($msec, 2, 3);
    }

    public function getName(): string
    {
        return $this->name;
    }
}