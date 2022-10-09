<?php

namespace Src\Crypto\Exchanges;

interface GetStream
{
    public function getStream(string $stream_name, array $options = []): string;
}