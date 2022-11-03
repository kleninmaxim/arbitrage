<?php

use Src\Support\Log;
use Src\Support\Math;

/**
 * @throws Exception
 */
function processWebsocketData(mixed $data, array $options): array
{
    if (!empty($options['markets'])) {
        if ($is = isUpdateSpotUserTrades($data, $options['markets']))
            return $is;

        if ($is = isUpdateOrSnapshotOrders($data, $options['markets']))
            return $is;
    }

    if (!empty($options['assets']))
        if ($is = isUpdateOrSnapshotSpotUserWallet($data, $options['assets']))
            return $is;

    if ($is = isSubscribed($data))
        return $is;

    if ($is = isLoggedIn($data))
        return $is;

    if ($is = isConnectionEstablished($data))
        return $is;

    return ['response' => 'error', 'data' => null];
}

function isUpdateOrSnapshotSpotUserWallet($data, $assets): array
{
    if (
        !empty($data['ts']) &&
        !empty($data['event']) &&
        !empty($data['topic']) &&
        !empty($data['data']) &&
        $data['topic'] == 'spot/wallet'
    ) {
        if (!empty($data['data']['balances']) && !empty($data['data']['reserved']) && $data['event'] == 'snapshot') {
            $exchange_balances = $data['data'];

            foreach ($assets as $asset) {
                if (isset($exchange_balances['balances'][$asset]) && isset($exchange_balances['reserved'][$asset])) {
                    $balances[$asset] = [
                        'free' => $exchange_balances['balances'][$asset],
                        'used' => $exchange_balances['reserved'][$asset],
                        'total' => Math::incrementNumber($exchange_balances['balances'][$asset] + $exchange_balances['reserved'][$asset], 0.00000001)
                    ];
                } else
                    $balances[$asset] = ['free' => 0, 'used' => 0, 'total' => 0];
            }

            return ['response' => 'isUpdateOrSnapshotSpotUserWallet', 'data' => $balances ?? []];
        }

        if (!empty($data['data']['currency']) && isset($data['data']['balance']) && isset($data['data']['reserved']) && $data['event'] == 'update')
            if (in_array($data['data']['currency'], $assets))
                return [
                    'response' => 'isUpdateOrSnapshotSpotUserWallet',
                    'data' => [
                        $data['data']['currency'] => [
                            'free' => $data['data']['balance'],
                            'used' => $data['data']['reserved'],
                            'total' => Math::incrementNumber($data['data']['balances'] + $data['data']['reserved'], 0.00000001)
                        ]
                    ]
                ];
    }

    return [];
}

function isUpdateOrSnapshotOrders($data, $markets): array
{
    if (
        !empty($data['ts']) &&
        !empty($data['event']) &&
        !empty($data['topic']) &&
        isset($data['data']) &&
        $data['topic'] == 'spot/orders'
    ) {
        if ($data['event'] == 'snapshot') {
            if (!empty($data['data']))
                Log::log(__FILE__, ['data' => $data]);

            return ['response' => 'isUpdateOrSnapshotOrders', 'data' => []];
        }

        if ($data['event'] == 'update') {
            $timestamp_in_seconds = floor($data['ts'] / 1000);

            return [
                'response' => 'isUpdateOrSnapshotOrders',
                'data' => [
                    'id' => $data['data']['order_id'],
                    'symbol' => $markets['origin'][$data['data']['pair']],
                    'side' => $data['data']['type'],
                    'price' => $data['data']['price'],
                    'amount' => $data['data']['quantity'],
                    'quote' => $data['data']['amount'],
                    'status' => $data['data']['status'],
                    'timestamp' => $timestamp_in_seconds,
                    'datetime' => date('Y--m-d H:i:s', $timestamp_in_seconds)
                ]
            ];
        }
    }

    return [];
}

function isUpdateSpotUserTrades($data, $markets): array
{
    if (
        !empty($data['ts']) &&
        !empty($data['event']) &&
        !empty($data['topic']) &&
        !empty($data['data']) &&
        $data['event'] == 'update' &&
        $data['topic'] == 'spot/user_trades'
    ) {
        $timestamp_in_seconds = floor($data['ts'] / 1000);

        return [
            'response' => 'isUpdateSpotUserTrades',
            'data' => [
                'trade_id' => $data['data']['trade_id'],
                'order_id' => $data['data']['order_id'],
                'symbol' => $markets['origin'][$data['data']['pair']],
                'type' => $data['data']['type'],
                'price' => $data['data']['price'],
                'amount' => $data['data']['quantity'],
                'quote' => $data['data']['amount'],
                'timestamp' => $timestamp_in_seconds,
                'trade_type' => $data['data']['exec_type'],
                'datetime' => date('Y--m-d H:i:s', $timestamp_in_seconds),
                'fee' => [
                    'amount' => $data['data']['commission_amount'],
                    'asset' => $data['data']['commission_currency']
                ]
            ]
        ];
    }

    return [];
}

function isSubscribed($data): array
{
    if (
        !empty($data['ts']) &&
        !empty($data['event']) &&
        !empty($data['id']) &&
        !empty($data['topic']) &&
        $data['event'] == 'subscribed'
    ) {
        $data['datetime'] = date('Y-m-d H:i:s', floor($data['ts'] / 1000));

        return ['response' => 'isSubscribed', 'data' => $data];
    }

    return [];
}

function isLoggedIn($data): array
{
    if (
        !empty($data['ts']) &&
        !empty($data['event']) &&
        !empty($data['id']) &&
        $data['event'] == 'logged_in'
    ) {
        $data['datetime'] = date('Y-m-d H:i:s', floor($data['ts'] / 1000));

        return ['response' => 'isLoggedIn', 'data' => $data];
    }

    return [];
}

/**
 * @throws Exception
 */
function isConnectionEstablished($data): array
{
    if (
        !empty($data['ts']) &&
        !empty($data['event']) &&
        !empty($data['code']) &&
        !empty($data['message']) &&
        !empty($data['session_id'])
    ) {
        if ($data['event'] == 'info' && $data['code'] == 1 && $data['message'] == 'connection established') {
            $data['datetime'] = date('Y-m-d H:i:s', floor($data['ts'] / 1000));

            return ['response' => 'isConnectionEstablished', 'data' => $data];
        }

        throw new Exception('Connect was unsuccessful');
    }

    return [];
}

function originCcxtMarketIds(array $fetch_markets, array $use_markets): array
{
    foreach (
        array_filter(
            $fetch_markets,
            fn($market) => in_array($market, $use_markets),
            ARRAY_FILTER_USE_KEY
        ) as $symbol => $market
    ) {
        $markets['origin'][$market['id']] = $symbol;
        $markets['ccxt'][$symbol] = $market;
    }
    return $markets ?? [];
}

function generateSignature(string $api_public, string $api_private, string $nonce): string
{
    return base64_encode(hash_hmac('sha512', $api_public . $nonce, $api_private, true));
}