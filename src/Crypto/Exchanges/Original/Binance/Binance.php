<?php

namespace Src\Crypto\Exchanges\Original\Binance;

class Binance
{
    protected string $name = 'binance';

    public function __construct()
    {

    }

    public static function init(): static
    {
        return new static();
    }

    public function getName(): string
    {
        return $this->name;
    }
}