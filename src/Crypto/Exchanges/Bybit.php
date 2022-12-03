<?php

namespace Src\Crypto\Exchanges;

use Src\Support\Http;
use Src\Support\Log;

class Bybit
{
    protected string $name = 'bybit';
    protected string $base_url = 'https://api.bybit.com';
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
        int $depth = 100 // under 200
    ): ?array
    {
        $response = $this->sendPublicRequest('get', '/spot/v3/public/quote/depth', ['symbol' => $symbol, 'limit' => $depth]);
        if (!empty($response['result']['time']))
            return $response['result'];
        return null;
    }

    public function getBalances(array $assets = []): ?array
    {
        $response = $this->sendPrivateRequest('get', '/spot/v3/private/account');

        if (!empty($response['result']['balances'])) {
            foreach ($response['result']['balances'] as $balance)
                $balances[$balance['coin']] = ['free' => $balance['free'], 'used' => $balance['locked'], 'total' => $balance['total']];

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
        string $side,   // Buy, Sell
        float $amount
    ): ?array
    {
        $order = $this->sendPrivateRequest(
            'post',
            '/spot/v3/private/order',
            ['symbol' => str_replace('/', '', $symbol), 'orderType' => $type, 'side' => $side, 'orderQty' => $amount]
        );
        if (!empty($order['result']['orderId'])) {
            $result = $order['result'];
            return [
                'id' => $result['orderId'],
                'symbol' => $result['symbol'],
                'side' => $result['side'],
                'price' => $result['orderPrice'],
                'amount' => $result['orderQty'],
                'quote' => $result['orderQty'] * $result['orderPrice'],
                'timestamp' => floor($result['createTime'] / 1000),
                'datetime' => date('Y-m-d H:i:s', floor($result['createTime'] / 1000))
            ];
        }
        Log::warning(['file' => __FILE__, '$order' => $order]);
        return null;
    }

    public function getName(): string
    {
        return $this->name;
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
        $get_params['api_key'] = $this->public_api;
        $get_params['timestamp'] = $this->getTimestamp();
        ksort($get_params);
        return $this->request(
            $method,
            $this->base_url . $url,
            array_merge($get_params, ['sign' => $this->generateSignature($get_params)])
        );
    }

    private function generateSignature($query): string
    {
        return hash_hmac('sha256', http_build_query($query), $this->private_api);
    }

    private function getTimestamp(): string
    {
        list($msec, $sec) = explode(' ', microtime());
        return $sec . substr($msec, 2, 3);
    }
}