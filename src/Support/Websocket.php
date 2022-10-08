<?php

namespace Src\Support;

use WebSocket\BadOpcodeException;
use WebSocket\Client;

class Websocket
{
    private Client $client;
    private static array $option = ['timeout' => 100];

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public static function init(string $url, array $option = []): static
    {
        return new static(new Client($url, array_merge(self::$option, $option)));
    }

    /**
     * @throws BadOpcodeException
     */
    public function send(array $message, string $opcode = 'text'): void
    {
        $this->client->send(json_encode($message), $opcode);
    }

    public function receive(): mixed
    {
        return json_decode($this->client->receive(), true);
    }

    public function close(): void
    {
        $this->client->close();
    }
}