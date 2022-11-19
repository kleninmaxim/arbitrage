<?php

namespace Src\Crypto\Exchanges\Original\Support;

interface HasWebsocketOrderbook
{
    public function messageRequestToSubscribeOrderbooks(array $symbols): string;
}