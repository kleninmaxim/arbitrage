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

    return ['response' => 'error', 'data' => null];
}

function isUpdateOrSnapshotSpotUserWallet($data, $assets): array
{
    if (
        !empty($data['type']) &&
        !empty($data['topic']) &&
        !empty($data['ts']) &&
        !empty($data['data']) &&
        !empty($data['data'][0]['e']) &&
        $data['data'][0]['e'] == 'outboundAccountInfo'
    ) {
        foreach ($data['data'] as $datum) {
            foreach ($datum['B'] as $item) {
                if ($datum['e'] != 'outboundAccountInfo')
                    Log::warning(['file' => __FILE__, 'message' => 'event not outboundAccountInfo', '$data' => $data]);

                if (in_array($item['a'], $assets)) {
                    $update_balances[$item['a']] = [
                        'free' => $item['f'],
                        'used' => $item['l'],
                        'total' => Math::incrementNumber($item['f'] + $item['l'], 0.00000001)
                    ];
                }
            }
        }

        return ['response' => 'isUpdateOrSnapshotSpotUserWallet', 'data' => $update_balances ?? []];
    }

    return [];
}

function isUpdateOrSnapshotOrders($data, $markets): array
{
    if (
        !empty($data['type']) &&
        !empty($data['topic']) &&
        !empty($data['ts']) &&
        !empty($data['data']) &&
        !empty($data['data'][0]['e']) &&
        $data['data'][0]['e'] == 'order'
    ) {
        if (count($data['data']) > 1)
            Log::warning(['file' => __FILE__, 'message' => 'get bybit more than one message in websocket isUpdateOrSnapshotOrders', '$data' => $data]);

        $timestamp_in_seconds = floor($data['data'][0]['O'] / 1000);

        $status = strtolower($data['data'][0]['X']);

        return [
            'response' => 'isUpdateOrSnapshotOrders',
            'data' => [
                'id' => $data['data'][0]['i'],
                'symbol' => $markets['origin'][$data['data'][0]['s']],
                'side' => strtolower($data['data'][0]['S']),
                'price' => $data['data'][0]['p'],
                'amount' => $data['data'][0]['q'],
                'quote' => round($data['data'][0]['p'] * $data['data'][0]['q'], 8),
                'status' => ($status == 'new' || $status == 'partially_filled') ? 'open' : $status,
                'filled' => $data['data'][0]['z'],
                'timestamp' => $timestamp_in_seconds,
                'datetime' => date('Y-m-d H:i:s', $timestamp_in_seconds)
            ]
        ];
    }

    return [];
}

function isUpdateSpotUserTrades($data, $markets): array
{
    if (
        !empty($data['type']) &&
        !empty($data['topic']) &&
        !empty($data['ts']) &&
        !empty($data['data']) &&
        !empty($data['data'][0]['e']) &&
        $data['data'][0]['e'] == 'ticketInfo'
    ) {
        if (count($data['data']) > 1)
            Log::warning(['file' => __FILE__, 'message' => 'get bybit more than one message in websocket isUpdateSpotUserTrades', '$data' => $data]);

        return [
            'response' => 'isUpdateSpotUserTrades',
            'data' => [
                'trade_id' => $data['data'][0]['T'],
                'order_id' => $data['data'][0]['o'],
                'symbol' => $markets['origin'][$data['data'][0]['s']],
                'trade_type' => ($data['data'][0]['m']) ? 'maker' : 'taker',
                'side' => strtolower($data['data'][0]['S']),
                'price' => $data['data'][0]['p'],
                'amount' => $data['data'][0]['q'],
                'quote' => round($data['data'][0]['p'] * $data['data'][0]['q'], 8),
                'timestamp' => $data['data'][0]['E'] / 1000,
                'datetime' => date('Y-m-d H:i:s', floor($data['data'][0]['E'] / 1000)),
                'fee' => [
                    'amount' => null,
                    'asset' => null
                ]
            ]
        ];
    }

    return [];
}

function isSubscribed($data): array
{
    if (
        !empty($data['success']) &&
        isset($data['ret_msg']) &&
        !empty($data['conn_id']) &&
        isset($data['req_id']) &&
        !empty($data['op']) &&
        $data['op'] == 'subscribe'
    ) {
        return ['response' => 'isSubscribed', 'data' => $data];
    }

    return [];
}

function isLoggedIn($data): array
{
    if (
        !empty($data['success']) &&
        isset($data['ret_msg']) &&
        !empty($data['conn_id']) &&
        isset($data['req_id']) &&
        !empty($data['op']) &&
        $data['op'] == 'auth'
    ) {
        return ['response' => 'isLoggedIn', 'data' => $data];
    }

    return [];
}

function generateSignature(string $api_private, string $expires): string
{
    return hash_hmac('sha256', 'GET/realtime' . $expires, $api_private);
}