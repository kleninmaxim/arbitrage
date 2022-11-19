<?php

namespace Src\Crypto\Exchanges\Original\Support;

interface Websocket
{
    public function processWebsocketData(mixed $data, array $options = []): array;
}