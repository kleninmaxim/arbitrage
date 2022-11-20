<?php

namespace Src\Crypto\Exchanges\Original\Bybit;

class Bybit
{
    protected string $name = 'bybit';

    public function __construct()
    {

    }

    public static function init(...$parameters): static
    {
        return new static(...$parameters);
    }

    public function getName(): string
    {
        return $this->name;
    }
}