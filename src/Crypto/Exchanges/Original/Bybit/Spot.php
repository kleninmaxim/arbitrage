<?php

namespace Src\Crypto\Exchanges\Original\Bybit;


use Exception;
use Src\Support\Http;
use Src\Support\Log;

class Spot extends Bybit
{
    protected string $base_url = 'https://api.bybit.com';
    protected string $public_api;
    protected string $private_api;
    private array $options;

    /**
     * @throws Exception
     */
    public function __construct(string $public_api, string $private_api, array $options = [])
    {
        $this->public_api = $public_api;
        $this->private_api = $private_api;

        if (!empty($options['symbols'])) {
            foreach ($options['symbols'] as $symbol) {
                $this->options['markets']['origin'][str_replace('/',  '', $symbol)] = $symbol;
            }
        } else {
            throw new Exception('Set $options[\'markets\']');
        }

        parent::__construct();
    }

    public function getMarketsWithOrigin()
    {
        return $this->options['markets'];
    }

    public function getBalances(array $assets = []): ?array
    {
        $response = $this->sendPrivateRequest(
            'get',
            '/spot/v3/private/account'
        );

        if (!empty($response['result']['balances'])) {
            foreach ($response['result']['balances'] as $response) {
                $balances[$response['coin']] = ['free' => $response['free'], 'used' => $response['locked'], 'total' => $response['total']];
            }

            if ($assets) {
                foreach ($assets as $asset) {
                    $modify_balances[$asset] = (!empty($balances[$asset])) ? $balances[$asset] : ['free' => 0, 'used' => 0, 'total' => 0];
                }

                return $modify_balances ?? [];
            }

            return $balances ?? [];
        }

        Log::warning(['file' => __FILE__, '$response' => $response]);
        return null;
    }

    public function createOrder(string $symbol, string $type, string $side, float $amount, float $price): ?array
    {
        $order = $this->sendPrivateRequest(
            'post',
            '/spot/v3/private/order',
            [
                'symbol' => str_replace('/', '', $symbol),
                'orderType' => mb_strtoupper($type),
                'side' => ucfirst($side),
                'orderQty' => $amount,
                'orderPrice' => $price,
            ]
        );

        if (!empty($order['result']['orderId'])) {
            $timestamp_in_seconds = floor($order['result']['createTime'] / 1000);

            return [
                'id' => $order['result']['orderId'],
                'symbol' => $this->options['markets']['origin'][$order['result']['symbol']],
                'side' => strtolower($order['result']['side']),
                'price' => $order['result']['orderPrice'],
                'amount' => $order['result']['orderQty'],
                'quote' => $order['result']['orderQty'] * $order['result']['orderPrice'],
                'status' => 'open',
                'filled' => null,
                'timestamp' => $timestamp_in_seconds,
                'datetime' => date('Y--m-d H:i:s', $timestamp_in_seconds)
            ];
        }
        Log::warning(['file' => __FILE__, '$order' => $order]);
        return null;
    }

    public function cancelOrder(string $order_id): ?array
    {
        $order = $this->sendPrivateRequest(
            'post',
            '/spot/v3/private/cancel-order',
            ['orderId' => $order_id]
        );

        if (!empty($order['result']['orderId'])) {
            return [
                'id' => $order['result']['orderId'],
                'symbol' => $this->options['markets']['origin'][$order['result']['symbol']]
            ];
        }
        Log::warning(['file' => __FILE__, '$order' => $order]);
        return null;
    }

    protected function request(string $method, string $url, array $query = [], array $header = []): array
    {
        return Http::$method($url, $query, $header);
    }

    private function sendPrivateRequest(string $method, string $url, array $get_params = []): array
    {
        $get_params['api_key'] = $this->public_api;
        $get_params['timestamp'] = $this->getTimestamp();

        ksort($get_params);

        return $this->request(
            $method,
            $this->base_url . $url,
            array_merge(
                $get_params,
                ['sign' => $this->generateSignature($get_params)]
            )
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