<?php

namespace Src\Crypto\Exchanges\Original\Exmo;

class Exmo
{
    protected string $name = 'exmo';

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