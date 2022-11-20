<?php

namespace Src\Crypto\Exchanges\Original\Bybit;

use Exception;
use Src\Crypto\Exchanges\Original\Support\HasWebsocketOrderbook;
use Src\Crypto\Exchanges\Original\Support\Websocket;
use Src\Standards\WebsocketDataStandard;
use Src\Support\Log;

class WebsocketDataSpotV3 extends Bybit implements Websocket, HasWebsocketOrderbook
{
    public array $websocket_options = ['id' => 1];
    const WEBSOCKET_ENDPOINT= 'wss://stream.bybit.com/spot/public/v3';

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

        return WebsocketDataStandard::error();
    }

    private function isOrderbook($data): array
    {
        if (!empty($data['data']) && !empty($data['topic']) && !empty($data['ts'])) {
            if (!empty($data['data']['s']) && !empty($data['data']['t']) && !empty($data['data']['b']) && !empty($data['data']['a'])) {
                return WebsocketDataStandard::orderbook(
                    $this->websocket_options['format_original_markets_to_common_format'][$data['data']['s']],
                    $data['data']['b'],
                    $data['data']['a'],
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
        if (!empty($data['op']) && !empty($data['success']) && !empty($data['req_id']) && !empty($data['ret_msg']) && !empty($data['conn_id']) && $data['op'] == 'subscribe' && $data['ret_msg'] == 'subscribe')
            return WebsocketDataStandard::original('isSubscribed', $data);

        return [];
    }

    private function originalSymbolFormat(string $symbol): string
    {
        $original_format = str_replace('/', '', $symbol);
        $this->websocket_options['format_original_markets_to_common_format'][$original_format] = $symbol;
        return $original_format;
    }

    private function getStreamNamesForOrderbook(array $symbols): array
    {
        $stream = 'orderbook.40.';
        $this->websocket_options['current_subscribed_stream'] = $stream;
        return array_map(fn($symbol) => $stream . $this->originalSymbolFormat($symbol), $symbols);
    }

    private function messageRequest(array $args): string
    {
        return json_encode([
            'req_id' => $this->websocket_options['id']++,
            'op' => 'subscribe',
            'args' => $args
        ]);
    }
}