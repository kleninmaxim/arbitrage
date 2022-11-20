<?php

namespace Src\Crypto\Exchanges\Original\Bybit;

class Bybit
{
    protected string $name = 'bybit';

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