<?php

namespace Src\Crypto\Exchanges\Original;

use Exception;
use Src\Crypto\Exchanges\GetStream;
use Src\Crypto\Exchanges\HasStreams;

class Binance implements GetStream
{
    use HasStreams;

    private string $name = 'binance';
    private string $websocket_base_endpoint = 'wss://stream.binance.com:9443';
    private array $streams = ['raw' => '/ws', 'combined' => '/stream'];
    private array $stream_names = [
        'partial_book_depth_stream' => '@depth',
        'individual_ticker_stream' => '@bookTicker'
    ];

    /**
     * @throws Exception
     */
    public function getRequest(array $all_streams, int $id = 1, bool $is_subscribe = true): array
    {
        return [
            'method' => $is_subscribe ? 'SUBSCRIBE' : 'UNSUBSCRIBE',
            'params' => $this->getStreams($all_streams),
            'id' => $id
        ];
    }

    public function getWebsocketEndpoint(bool $combine = true): string
    {
        return $this->websocket_base_endpoint . ($combine ? $this->streams['combined'] : $this->streams['raw']);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPartialBookDepthStream(array|string $symbols = '', int $depth = 5, bool $fast = true, array $custom = []): array
    {
        if ($custom) {
            $options = $custom;
        } elseif (is_string($symbols)) {
            $options = [['symbol' => $symbols, 'level' => $depth, 'fast' => $fast]];
        } elseif (is_array($symbols)) {
            foreach ($symbols as $symbol)
                $options[] = ['symbol' => $symbol, 'level' => $depth, 'fast' => $fast];
        }

        return $options ?? [];
    }

    /**
     * @throws Exception
     */
    public function getStream(string $stream_name, array $options = []): string
    {
        if ($stream_name == 'partial_book_depth_stream') {
            if (!isset($options['symbol']))
                throw new Exception('You have to add symbol for ' . $stream_name);

            if (!isset($options['level']) || !is_int($options['level']) || !in_array($options['level'], [5, 10, 20]))
                throw new Exception('You have to add level for ' . $stream_name);

            return mb_strtolower(str_replace('/', '', $options['symbol'])) .
                $this->stream_names[$stream_name] . $options['level'] .
                (isset($options['fast']) ? '@100ms' : '');
        } elseif ($stream_name == 'individual_ticker_stream') {
            if (!isset($options['symbol']))
                throw new Exception('You have to add symbol for ' . $stream_name);

            return mb_strtolower(str_replace('/', '', $options['symbol'])) . $this->stream_names[$stream_name];
        }

        throw new Exception('Have no such stream name' . $stream_name);
    }

    /**
     * @throws Exception
     */
    public function processWebsocketData(mixed $data, array $streams): array
    {
        if ($format_websocket_data = $this->isWebsocketData($data, $streams))
            return $format_websocket_data;

        if ($result = $this->isResultWebsocketData($data))
            return $result;

        throw new Exception('Bad data');
    }

    /**
     * @throws Exception
     */
    private function isResultWebsocketData(array $data): array
    {
        if (empty($data['result']) && !empty($data['id'])) {
            if (is_null($data['result']) && $data['id'] == 1)
                return [
                    'response' => 'result',
                    'data' => null
                ];

            throw new Exception('The request sent was unsuccessful');
        }

        return [];
    }

    /**
     * @throws Exception
     */
    private function isWebsocketData(mixed $data, array $streams): array
    {
        if (!empty($data['stream']) && !empty($data['data']))
            return match ($streams[$data['stream']]['stream_name']) {
                'partial_book_depth_stream' => $this->formatOrderbook($data, $streams),
                default => throw new Exception('Bad Stream')
            };

        return [];
    }

    /**
     * @throws Exception
     */
    private function formatOrderbook(mixed $data, array $streams): array
    {
        if (!empty($data['data']['bids']) && !empty($data['data']['asks']) && !empty($data['data']['lastUpdateId']))
            return [
                'response' => 'orderbook',
                'data' => [
                    'symbol' => $streams[$data['stream']]['options']['symbol'],
                    'bids' => $data['data']['bids'],
                    'asks' => $data['data']['asks'],
                    'timestamp' => null,
                    'datetime' => null,
                    'nonce' => $data['data']['lastUpdateId'],
                ]
            ];

        throw new Exception('Empty Stream data');
    }
}