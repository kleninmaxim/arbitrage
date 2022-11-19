<?php

namespace Src\Crypto\Exchanges\Original\Binance;

use Exception;
use Src\Crypto\Exchanges\Original\Support\HasWebsocketOrderbook;
use Src\Crypto\Exchanges\Original\Support\Websocket;
use Src\Standards\WebsocketDataStandard;
use Src\Support\Log;

class WebsocketMarketStream extends Binance implements Websocket, HasWebsocketOrderbook
{
    public array $websocket_options = ['id' => 1];
    const WEBSOCKET_ENDPOINT = 'wss://stream.binance.com:9443/stream';

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

        if ($is = $this->isResult($data))
            return $is;

        return WebsocketDataStandard::error();
    }

    private function isOrderbook(array $data): ?array
    {
        if (!empty($data['stream']) && !empty($data['data'])) {
            if (!empty($data['data']['bids']) && !empty($data['data']['asks']) && !empty($data['data']['lastUpdateId'])) {
                return WebsocketDataStandard::orderbook(
                    $this->websocket_options['format_original_markets_to_common_format'][str_replace($this->websocket_options['current_subscribed_stream'], '', $data['stream'])],
                    $data['data']['bids'],
                    $data['data']['asks'],
                    null,
                    null,
                    $data['data']['lastUpdateId'],
                    $this->name
                );
            } else
                Log::warning(['empty $data[\'data\'][\'bids\'] or $data[\'data\'][\'asks\'] or $data[\'data\'][\'lastUpdateId\']', '$data' => $data, 'file' => __FILE__]);
        }

        return null;
    }

    /**
     * @throws Exception
     */
    private function isResult(array $data): ?array
    {
        if (empty($data['result']) && !empty($data['id'])) {
            if (is_null($data['result']) && $data['id'] == 1)
                return WebsocketDataStandard::original('isResult');

            throw new Exception('The request sent was unsuccessful');
        }

        return null;
    }

    private function originalSymbolFormat(string $symbol): string
    {
        $original_format = mb_strtolower(str_replace('/', '', $symbol));
        $this->websocket_options['format_original_markets_to_common_format'][$original_format] = $symbol;
        return $original_format;
    }

    private function getStreamNamesForOrderbook(array $symbols): array
    {
        $stream = '@depth10@100ms';
        $this->websocket_options['current_subscribed_stream'] = $stream;
        return array_map(fn($symbol) => $this->originalSymbolFormat($symbol) . $stream, $symbols);
    }

    private function messageRequest(array $params): string
    {
        return json_encode([
            'method' => 'SUBSCRIBE',
            'params' => $params,
            'id' => $this->websocket_options['id']++
        ]);
    }
}