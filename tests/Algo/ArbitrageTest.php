<?php

namespace Tests\Algo;

use Src\Algo\Arbitrage;
use PHPUnit\Framework\TestCase;
use Src\Crypto\Ccxt;

class ArbitrageTest extends TestCase
{
    public function setUp(): void
    {
        $this->exchange = $this->createMock(Ccxt::class);
        $this->market_discovery = $this->createMock(Ccxt::class);

        $this->exchange->name = 'exmo';
        $this->market_discovery->name = 'binance';

        $this->assets = ['BTC', 'ETH', 'USDT'];

        $exchange_balances = [
            'BTC' => ['free' => 1, 'used' => 0, 'total' => 1],
            'ETH' => ['free' => 10, 'used' => 0, 'total' => 10],
            'USDT' => ['free' => 10000, 'used' => 0, 'total' => 10000]
        ];

        $market_discovery_balances = [
            'BTC' => ['free' => 1.1, 'used' => 0, 'total' => 1.1],
            'ETH' => ['free' => 11, 'used' => 0, 'total' => 101],
            'USDT' => ['free' => 10100, 'used' => 0, 'total' => 10100]
        ];

        $this->exchange->expects($this->once())->method('getBalances')->with($this->assets)->willReturn($exchange_balances);

        $this->market_discovery->expects($this->once())->method('getBalances')->with($this->assets)->willReturn($market_discovery_balances);

        $this->exchange->expects($this->once())->method('getOpenOrders')->willReturn([]);

        $this->arbitrage = new Arbitrage($this->exchange, $this->market_discovery, $this->assets);
    }

    /**
     * @test
     */
    public function test()
    {

    }
}
