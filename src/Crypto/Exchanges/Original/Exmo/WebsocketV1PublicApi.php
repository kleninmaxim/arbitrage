<?php

namespace Src\Crypto\Exchanges\Original\Exmo;

use Exception;
use Src\Crypto\Exchanges\Original\Support\HasWebsocketOrderbook;
use Src\Crypto\Exchanges\Original\Support\Websocket;
use Src\Standards\WebsocketDataStandard;
use Src\Support\Log;

class WebsocketV1PublicApi extends Exmo implements Websocket, HasWebsocketOrderbook
{
    public array $websocket_options = [];
    const WEBSOCKET_ENDPOINT= 'wss://ws-api.exmo.com:443/v1/public';

    public function messageRequestToSubscribeOrderbooks(array $symbols): string
    {
        return $this->messageRequest($this->getStreamNamesForOrderbook($symbols));
    }

    /**
     * @throws Exception
     */
    public function processWebsocketData(mixed $data, array $options = []): array
    {
        if ($is = $this->isOrderbook($data))
            return $is;

        if ($is = $this->isSubscribed($data))
            return $is;

        if ($is = $this->isConnectionEstablished($data))
            return $is;

        return WebsocketDataStandard::error();
    }


    private function isOrderbook($data): array
    {
        if (!empty($data['ts']) && !empty($data['event']) && !empty($data['data']) && !empty($data['topic'] && str_contains($data['topic'], 'spot/order_book_snapshots:'))) {
            if (!empty($data['data']['bid']) && !empty($data['data']['ask']) && $data['event'] == 'update') {
                foreach ($data['data']['bid'] as $key => $datum)
                    unset($data['data']['bid'][$key][2]);

                foreach ($data['data']['ask'] as $key => $datum)
                    unset($data['data']['ask'][$key][2]);

                return WebsocketDataStandard::orderbook(
                    $this->websocket_options['format_original_markets_to_common_format'][str_replace($this->websocket_options['current_subscribed_stream'], '', $data['topic'])],
                    $data['data']['bid'],
                    $data['data']['ask'],
                    $data['ts'],
                    date('Y-m-d H:i:s', floor($data['ts'] / 1000)),
                    null,
                    $this->name
                );
            } else
                Log::warning(['empty $data[\'data\'][\'bid\'] or $data[\'data\'][\'ask\'] or $data[\'event\'][\'update\']', '$data' => $data, 'file' => __FILE__]);
        }

        return [];
    }

    private function isSubscribed($data): array
    {
        if (!empty($data['ts']) && !empty($data['event']) && !empty($data['id']) && !empty($data['topic']) && $data['event'] == 'subscribed') {
            $data['datetime'] = date('Y-m-d H:i:s', floor($data['ts'] / 1000));

            return WebsocketDataStandard::original('isSubscribed', $data);
        }

        return [];
    }

    /**
     * @throws Exception
     */
    private function isConnectionEstablished($data): array
    {
        if (!empty($data['ts']) && !empty($data['event']) && !empty($data['code']) && !empty($data['message']) && !empty($data['session_id'])) {
            if ($data['event'] == 'info' && $data['code'] == 1 && $data['message'] == 'connection established') {
                $data['datetime'] = date('Y-m-d H:i:s', floor($data['ts'] / 1000));

                return WebsocketDataStandard::original('isConnectionEstablished', $data);
            }

            throw new Exception('Connect was unsuccessful');
        }

        return [];
    }

    private function originalSymbolFormat(string $symbol): string
    {
        $original_format = str_replace('/', '_', $symbol);
        $this->websocket_options['format_original_markets_to_common_format'][$original_format] = $symbol;
        return $original_format;
    }

    private function getStreamNamesForOrderbook(array $symbols): array
    {
        $stream = 'spot/order_book_snapshots:';
        $this->websocket_options['current_subscribed_stream'] = $stream;
        return array_map(fn($symbol) => $stream . $this->originalSymbolFormat($symbol), $symbols);
    }

    private function messageRequest(array $topics): string
    {
        return json_encode([
            'method' => 'subscribe',
            'id' => 1,
            'topics' => $topics
        ]);
    }
}