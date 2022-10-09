<?php

namespace Src\Crypto\Exchanges\Original;

use Exception;
use Src\Crypto\Exchanges\GetStream;
use Src\Crypto\Exchanges\HasStreams;

class Exmo implements GetStream
{
    use HasStreams;

    private string $name = 'exmo';
    private string $websocket_connection = 'wss://ws-api.exmo.com:443/v1/public';
    private array $stream_names = [
        'order_book_snapshots' => 'spot/order_book_snapshots:'
    ];

    /**
     * @throws Exception
     */
    public function getRequest(array $all_streams, int $id = 1, bool $is_subscribe = true): array
    {
        return [
            'id' => $id,
            'method' => $is_subscribe ? 'subscribe' : 'unsubscribe',
            'topics' => $this->getStreams($all_streams),
        ];
    }

    public function getWebsocketEndpoint(): string
    {
        return $this->websocket_connection;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function OrderBookSnapshots(array|string $symbols = '', array $custom = []): array
    {
        if ($custom) {
            $options = $custom;
        } elseif (is_string($symbols)) {
            $options = [['symbol' => $symbols]];
        } elseif (is_array($symbols)) {
            foreach ($symbols as $symbol)
                $options[] = ['symbol' => $symbol];
        }

        return $options ?? [];
    }

    /**
     * @throws Exception
     */
    public function getStream(string $stream_name, array $options = []): string
    {
        if ($stream_name == 'order_book_snapshots') {
            if (!isset($options['symbol']))
                throw new Exception('You have to add symbol for ' . $stream_name);

            return $this->stream_names[$stream_name] . str_replace('/', '_', $options['symbol']);
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

        if ($greeting_message = $this->isGreetingMessageWebsocketData($data))
            return $greeting_message;

        if ($result = $this->isResponseWebsocketData($data))
            return $result;

        throw new Exception('Bad data');
    }

    /**
     * @throws Exception
     */
    private function isGreetingMessageWebsocketData(array $data): array
    {
        if (!empty($data['ts']) && !empty($data['event']) && !empty($data['code']) && !empty($data['message']) && !empty($data['session_id'])) {
            if ($data['event'] == 'info' && $data['code'] == 1 && $data['message'] == 'connection established') {
                $data['timestamp'] = $data['ts'] / 1000;

                unset($data['ts']);

                return [
                    'response' => 'greeting_message',
                    'data' => $data
                ];
            }

            throw new Exception('Connect was unsuccessful');
        }

        return [];
    }

    /**
     * @throws Exception
     */
    private function isResponseWebsocketData(array $data): array
    {
        if (!empty($data['ts']) && !empty($data['event']) && !empty($data['id']) && !empty($data['topic'])) {
            if ($data['id'] == 1) {
                $data['timestamp'] = $data['ts'] / 1000;

                unset($data['ts']);

                return [
                    'response' => 'response',
                    'data' => $data
                ];
            }

            throw new Exception('The request sent was unsuccessful');
        }

        return [];
    }


    /**
     * @throws Exception
     */
    private function isWebsocketData(mixed $data, array $streams): array
    {
        if (!empty($data['ts']) && !empty($data['event']) && !empty($data['data']) && !empty($data['topic']))
            return match ($streams[$data['topic']]['stream_name']) {
                'order_book_snapshots' => $this->formatOrderbook($data, $streams),
                default => throw new Exception('Bad Stream')
            };

        return [];
    }

    /**
     * @throws Exception
     */
    private function formatOrderbook(mixed $data, array $streams): array
    {
        if (!empty($data['data']['bid']) && !empty($data['data']['ask']) && $data['event'] == 'update') {
            foreach ($data['data']['bid'] as $key => $datum)
                unset($data['data']['bid'][$key][2]);

            foreach ($data['data']['ask'] as $key => $datum)
                unset($data['data']['ask'][$key][2]);

            return [
                'response' => 'orderbook',
                'data' => [
                    'symbol' => $streams[$data['topic']]['options']['symbol'],
                    'bids' => $data['data']['bid'],
                    'asks' => $data['data']['ask'],
                    'timestamp' => $data['ts'],
                    'datetime' => date('Y-m-d H:i:s', floor($data['ts'] / 1000)),
                    'nonce' => null,
                    'exchange' => $this->name
                ]
            ];
        }

        throw new Exception('Empty Stream data');
    }

}