<?php

namespace Tests\Crypto;

use ccxt\Exchange;
use Exception;
use Src\Crypto\Ccxt;
use PHPUnit\Framework\TestCase;

class CcxtTest extends TestCase
{
    /** @test */
    public function class_has_exchange_attribute_and_init_exchange_method()
    {
        $this->assertClassHasAttribute('exchange', Ccxt::class);

        $this->assertTrue(
            method_exists(Ccxt::class, 'init'),
            'Class does not have method init'
        );

        $ccxt = Ccxt::init('binance');

        $this->assertInstanceOf('ccxt\binance', $ccxt->getExchange());
    }

    /**
     * @test
     * @throws Exception
     */
    public function it_can_get_orderbook()
    {
        $symbol = 'BTC/USDT';
        $depth = 5;

        $orderbook = [
            'symbol' => $symbol,
            'bids' => [
                [
                    0 => 19479.49,
                    1 => 0.01,
                ],
                [
                    0 => 19479.4,
                    1 => 0.00396,
                ],
                [
                    0 => 19479.39,
                    1 => 0.00445,
                ],
                [
                    0 => 19479.38,
                    1 => 0.20127,
                ],
                [
                    0 => 19479.11,
                    1 => 0.22756,
                ],
            ],
            'asks' => [
                [
                    0 => 19479.5,
                    1 => 0.01176,
                ],
                [
                    0 => 19479.54,
                    1 => 0.00789,
                ],
                [
                    0 => 19479.59,
                    1 => 0.01,
                ],
                [
                    0 => 19479.6,
                    1 => 0.00999,
                ],
                [
                    0 => 19479.97,
                    1 => 0.01026,
                ],
            ],
            'timestamp' => null,
            'datetime' => null,
            'nonce' => 25426381835,
        ];

        $ccxt_exchange = $this->getMockBuilder(Exchange::class)->addMethods(['fetch_order_book'])->getMock();

        $ccxt_exchange->expects($this->once())
            ->method('fetch_order_book')->with($symbol, $depth)->willReturn($orderbook);

        $ccxt = new Ccxt($ccxt_exchange);

        $this->assertEquals($orderbook, $ccxt->getOrderBook($symbol, $depth));
    }

    /**
     * @test
     * @throws Exception
     */
    public function it_can_get_open_orders()
    {
        $open_orders = [
            [
                'id' => 31651024847,
                'clientOrderId' => 0,
                'datetime' => '2022-10-08T09:17:52.000Z',
                'timestamp' => 1665220672000,
                'lastTradeTimestamp' => null,
                'status' => 'open',
                'symbol' => 'BTC/USDT',
                'type' => 'limit',
                'timeInForce' => null,
                'postOnly' => null,
                'side' => 'buy',
                'price' => 19496.77,
                'stopPrice' => null,
                'cost' => 99.99542391,
                'amount' =>  0.00512882,
                'filled' => 0,
                'remaining' => 0.00512882,
                'average' => null,
                'trades' => [],
                'fee' => ['cost' => '', 'currency' => 'USDT'],
                'info' => [
                    'order_id' => 31651024847,
                    'client_id' => 0,
                    'created' => 1665220672,
                    'type' => 'buy',
                    'pair' => 'BTC_USDT',
                    'quantity' => 0.00512882,
                    'price' => 19496.77,
                    'amount' => 99.99542391
                ]
            ]
        ];

        $ccxt_exchange = $this->createMock(Exchange::class);
        $ccxt_exchange->expects($this->once())->method('fetch_open_orders')->willReturn($open_orders);

        $ccxt = new Ccxt($ccxt_exchange);

        $this->assertEquals($open_orders, $ccxt->getOpenOrders());
    }

    /**
     * @test
     * @throws Exception
     */
    public function it_can_get_balances()
    {
        $balances = [
            'BTC' => ['free' => 1, 'used' => 0, 'total' => 1],
            'USDT' => ['free' => 1000, 'used' => 100, 'total' => 1100],
            'ETH' => ['free' => 3, 'used' => 0, 'total' => 3]
        ];

        $ccxt_exchange = $this->createMock(Exchange::class);
        $ccxt_exchange->expects($this->once())->method('fetch_balance')->willReturn($balances);

        $ccxt = new Ccxt($ccxt_exchange);

        $this->assertEquals($balances, $ccxt->getBalances());
    }

    /**
     * @test
     * @throws Exception
     */
    public function it_can_get_balances_for_some_assets()
    {
        $balances = [
            'BTC' => ['free' => 1, 'used' => 0, 'total' => 1],
            'USDT' => ['free' => 1000, 'used' => 100, 'total' => 1100],
            'ETH' => ['free' => 3, 'used' => 0, 'total' => 3]
        ];

        $ccxt_exchange = $this->createMock(Exchange::class);
        $ccxt_exchange->expects($this->once())->method('fetch_balance')->willReturn($balances);

        $ccxt = new Ccxt($ccxt_exchange);

        unset($balances['USDT']);

        self::assertEquals($balances, $ccxt->getBalances(['BTC', 'ETH']));
    }

    /**
     * @test
     * @throws Exception
     */
    public function it_can_get_balances_and_add_some_assets()
    {
        $balances = [
            'BTC' => ['free' => 1, 'used' => 0, 'total' => 1],
            'USDT' => ['free' => 1000, 'used' => 100, 'total' => 1100],
            'ETH' => ['free' => 3, 'used' => 0, 'total' => 3]
        ];

        $ccxt_exchange = $this->createMock(Exchange::class);
        $ccxt_exchange->expects($this->once())->method('fetch_balance')->willReturn($balances);

        $ccxt = new Ccxt($ccxt_exchange);

        $balances['DOGE'] = ['free' => 0, 'used' => 0, 'total' => 0];

        $this->assertEquals($balances, $ccxt->getBalances(['BTC', 'USDT', 'ETH', 'DOGE']));
    }

    /**
     * @test
     * @throws Exception
     */
    public function it_can_create_order()
    {
        $symbol = 'BTC/USDT';
        $type = 'limit';
        $side = 'buy';
        $amount = 0.1;
        $price = 10000;

        $create_order = [
            'id' => 31651024847,
            'info' => ['id' => 31651024847]
        ];

        $ccxt_exchange = $this->createMock(Exchange::class);
        $ccxt_exchange->expects($this->once())->method('create_order')->with($symbol, $type, $side, $amount, $price)->willReturn($create_order);

        $ccxt = new Ccxt($ccxt_exchange);

        $this->assertEquals($create_order, $ccxt->createOrder($symbol, $type, $side, $amount, $price));
    }

    /**
     * @test
     * @throws Exception
     */
    public function it_can_cancel_order()
    {
        $order_id = 31651024847;

        $cancel_order = [
            'id' => $order_id,
            'info' => ['id' => $order_id]
        ];

        $ccxt_exchange = $this->createMock(Exchange::class);
        $ccxt_exchange->expects($this->once())->method('cancel_order')->with($order_id)->willReturn($cancel_order);

        $ccxt = new Ccxt($ccxt_exchange);

        $this->assertEquals($cancel_order, $ccxt->cancelOrder($order_id));
    }

    /**
     * @test
     * @throws Exception
     */
    public function it_can_cancel_all_orders()
    {
        $order_id = [31651024847, 31651024848];

        $open_orders = [
            [
                'id' => $order_id[0],
                'clientOrderId' => 0,
                'datetime' => '2022-10-08T09:17:52.000Z',
                'timestamp' => 1665220672000,
                'lastTradeTimestamp' => null,
                'status' => 'open',
                'symbol' => 'BTC/USDT',
                'type' => 'limit',
                'timeInForce' => null,
                'postOnly' => null,
                'side' => 'buy',
                'price' => 19496.77,
                'stopPrice' => null,
                'cost' => 99.99542391,
                'amount' =>  0.00512882,
                'filled' => 0,
                'remaining' => 0.00512882,
                'average' => null,
                'trades' => [],
                'fee' => ['cost' => '', 'currency' => 'USDT'],
                'info' => [
                    'order_id' => 31651024847,
                    'client_id' => 0,
                    'created' => 1665220672,
                    'type' => 'buy',
                    'pair' => 'BTC_USDT',
                    'quantity' => 0.00512882,
                    'price' => 19496.77,
                    'amount' => 99.99542391
                ]
            ],
            [
                'id' => $order_id[1],
                'clientOrderId' => 0,
                'datetime' => '2022-10-08T09:17:52.000Z',
                'timestamp' => 1665220672000,
                'lastTradeTimestamp' => null,
                'status' => 'open',
                'symbol' => 'BTC/USDT',
                'type' => 'limit',
                'timeInForce' => null,
                'postOnly' => null,
                'side' => 'buy',
                'price' => 19300.77,
                'stopPrice' => null,
                'cost' => 99.99542391,
                'amount' =>  0.00512882,
                'filled' => 0,
                'remaining' => 0.00512882,
                'average' => null,
                'trades' => [],
                'fee' => ['cost' => '', 'currency' => 'USDT'],
                'info' => [
                    'order_id' => 31651024847,
                    'client_id' => 0,
                    'created' => 1665220672,
                    'type' => 'buy',
                    'pair' => 'BTC_USDT',
                    'quantity' => 0.00512882,
                    'price' => 19496.77,
                    'amount' => 99.99542391
                ]
            ]
        ];

        $cancel_orders = [
            [
                'id' => $order_id[0],
                'info' => ['id' => $order_id[0]]
            ],
            [
                'id' => $order_id[1],
                'info' => ['id' => $order_id[1]]
            ]
        ];

        $ccxt_exchange = $this->createMock(Exchange::class);
        $ccxt_exchange->expects($this->once())->method('fetch_open_orders')->willReturn($open_orders);
        $ccxt_exchange->expects($this->exactly(2))->method('cancel_order')
            ->withConsecutive([$order_id[0], 'BTC/USDT'], [$order_id[1], 'BTC/USDT'])
            ->willReturnOnConsecutiveCalls(...$cancel_orders);

        $ccxt = new Ccxt($ccxt_exchange);

        $this->assertEquals($cancel_orders, $ccxt->cancelAllOrder());
    }

    /**
     * @test
     * @throws Exception
     */
    public function it_can_fetch_markets()
    {
        $markets = [
            [
                'id' => 'ETHBTC',
                'lowercaseId' => 'ethbtc',
                'symbol' => 'ETH/BTC',
                'base' => 'ETH',
                'quote' => 'BTC',
                'settle' => null,
                'baseId' => 'ETH',
                'quoteId' => 'BTC',
                'settleId' => null,
                'type' => 'spot',
                'spot' => 1,
                'margin' => 1,
                'swap' => null,
                'future' => null,
                'delivery' => null,
                'option' => null,
                'active' => 1,
                'contract' => null,
                'linear' => null,
                'inverse' => null,
                'taker' => 0.001,
                'maker' => 0.001,
                'contractSize' => null,
                'expiry' => null,
                'expiryDatetime' => null,
                'strike' => null,
                'optionType' => null,
                'precision' => [
                    'amount' => 4,
                    'price' => 6,
                    'base' => 8,
                    'quote' => 8
                ],
                'limits' => [
                    'leverage' => [
                        'min' => null,
                        'max' => null
                    ],
                    'amount' => [
                        'min' => 0.0001,
                        'max' => 100000
                    ],
                    'price' => [
                        'min' => 1.0E-6,
                        'max' => 922327
                    ],
                    'cost' => [
                        'min' => 0.0001,
                        'max' => null
                    ],
                    'market' => [
                        'min' => 0,
                        'max' => 2040.71151974
                    ]
                ],
                'info' => []
            ],
            [
                'id' => 'BTCUSDT',
                'lowercaseId' => 'btcusdt',
                'symbol' => 'BTC/USDT',
                'base' => 'BTC',
                'quote' => 'USDT',
                'settle' => null,
                'baseId' => 'BTC',
                'quoteId' => 'USDT',
                'settleId' => null,
                'type' => 'spot',
                'spot' => 1,
                'margin' => 1,
                'swap' => null,
                'future' => null,
                'delivery' => null,
                'option' => null,
                'active' => 1,
                'contract' => null,
                'linear' => null,
                'inverse' => null,
                'taker' => 0.001,
                'maker' => 0.001,
                'contractSize' => null,
                'expiry' => null,
                'expiryDatetime' => null,
                'strike' => null,
                'optionType' => null,
                'precision' => [
                    'amount' => 4,
                    'price' => 6,
                    'base' => 8,
                    'quote' => 8
                ],
                'limits' => [
                    'leverage' => [
                        'min' => null,
                        'max' => null
                    ],
                    'amount' => [
                        'min' => 0.0001,
                        'max' => 100000
                    ],
                    'price' => [
                        'min' => 1.0E-6,
                        'max' => 922327
                    ],
                    'cost' => [
                        'min' => 0.0001,
                        'max' => null
                    ],
                    'market' => [
                        'min' => 0,
                        'max' => 2040.71151974
                    ]
                ],
                'info' => []
            ]
        ];

        $ccxt_exchange = $this->createMock(Exchange::class);
        $ccxt_exchange->expects($this->once())->method('fetch_markets')->willReturn($markets);

        $ccxt = new Ccxt($ccxt_exchange);

        unset($markets[0]);

        $this->assertEquals($markets, $ccxt->fetchMarkets(['BTC', 'USDT']));
    }

    /**
     * @test
     * @throws Exception
     */
    public function it_can_fetch_markets_have_no_active()
    {
        $markets = [
            [
                'id' => 'ETHBTC',
                'lowercaseId' => 'ethbtc',
                'symbol' => 'ETH/BTC',
                'base' => 'ETH',
                'quote' => 'BTC',
                'settle' => null,
                'baseId' => 'ETH',
                'quoteId' => 'BTC',
                'settleId' => null,
                'type' => 'spot',
                'spot' => 1,
                'margin' => 1,
                'swap' => null,
                'future' => null,
                'delivery' => null,
                'option' => null,
                'active' => null,
                'contract' => null,
                'linear' => null,
                'inverse' => null,
                'taker' => 0.001,
                'maker' => 0.001,
                'contractSize' => null,
                'expiry' => null,
                'expiryDatetime' => null,
                'strike' => null,
                'optionType' => null,
                'precision' => [
                    'amount' => 4,
                    'price' => 6,
                    'base' => 8,
                    'quote' => 8
                ],
                'limits' => [
                    'leverage' => [
                        'min' => null,
                        'max' => null
                    ],
                    'amount' => [
                        'min' => 0.0001,
                        'max' => 100000
                    ],
                    'price' => [
                        'min' => 1.0E-6,
                        'max' => 922327
                    ],
                    'cost' => [
                        'min' => 0.0001,
                        'max' => null
                    ],
                    'market' => [
                        'min' => 0,
                        'max' => 2040.71151974
                    ]
                ],
                'info' => []
            ],
            [
                'id' => 'BTCUSDT',
                'lowercaseId' => 'btcusdt',
                'symbol' => 'BTC/USDT',
                'base' => 'BTC',
                'quote' => 'USDT',
                'settle' => null,
                'baseId' => 'BTC',
                'quoteId' => 'USDT',
                'settleId' => null,
                'type' => 'spot',
                'spot' => 1,
                'margin' => 1,
                'swap' => null,
                'future' => null,
                'delivery' => null,
                'option' => null,
                'active' => null,
                'contract' => null,
                'linear' => null,
                'inverse' => null,
                'taker' => 0.001,
                'maker' => 0.001,
                'contractSize' => null,
                'expiry' => null,
                'expiryDatetime' => null,
                'strike' => null,
                'optionType' => null,
                'precision' => [
                    'amount' => 4,
                    'price' => 6,
                    'base' => 8,
                    'quote' => 8
                ],
                'limits' => [
                    'leverage' => [
                        'min' => null,
                        'max' => null
                    ],
                    'amount' => [
                        'min' => 0.0001,
                        'max' => 100000
                    ],
                    'price' => [
                        'min' => 1.0E-6,
                        'max' => 922327
                    ],
                    'cost' => [
                        'min' => 0.0001,
                        'max' => null
                    ],
                    'market' => [
                        'min' => 0,
                        'max' => 2040.71151974
                    ]
                ],
                'info' => []
            ]
        ];

        $ccxt_exchange = $this->createMock(Exchange::class);
        $ccxt_exchange->expects($this->exactly(2))->method('fetch_markets')->willReturn($markets);

        $ccxt = new Ccxt($ccxt_exchange);

        $this->assertEquals($markets, $ccxt->fetchMarkets(['BTC', 'ETH', 'USDT'], false));
        $this->assertEmpty($ccxt->fetchMarkets(['BTC', 'ETH', 'USDT']));
    }

    /**
     * @test
     * @throws Exception
     */
    public function it_get_markets_formatted_has_precisions()
    {
        $markets = [
            [
                'id' => 'ETHBTC',
                'lowercaseId' => 'ethbtc',
                'symbol' => 'ETH/BTC',
                'base' => 'ETH',
                'quote' => 'BTC',
                'settle' => null,
                'baseId' => 'ETH',
                'quoteId' => 'BTC',
                'settleId' => null,
                'type' => 'spot',
                'spot' => 1,
                'margin' => 1,
                'swap' => null,
                'future' => null,
                'delivery' => null,
                'option' => null,
                'active' => 1,
                'contract' => null,
                'linear' => null,
                'inverse' => null,
                'taker' => 0.001,
                'maker' => 0.001,
                'contractSize' => null,
                'expiry' => null,
                'expiryDatetime' => null,
                'strike' => null,
                'optionType' => null,
                'precision' => [
                    'amount' => 4,
                    'price' => 6,
                    'base' => 8,
                    'quote' => 8
                ],
                'limits' => [
                    'leverage' => [
                        'min' => null,
                        'max' => null
                    ],
                    'amount' => [
                        'min' => 0.0001,
                        'max' => 100000
                    ],
                    'price' => [
                        'min' => 1.0E-6,
                        'max' => 922327
                    ],
                    'cost' => [
                        'min' => 0.0001,
                        'max' => null
                    ],
                    'market' => [
                        'min' => 0,
                        'max' => 2040.71151974
                    ]
                ],
                'info' => []
            ],
            [
                'id' => 'BTCUSDT',
                'lowercaseId' => 'btcusdt',
                'symbol' => 'BTC/USDT',
                'base' => 'BTC',
                'quote' => 'USDT',
                'settle' => null,
                'baseId' => 'BTC',
                'quoteId' => 'USDT',
                'settleId' => null,
                'type' => 'spot',
                'spot' => 1,
                'margin' => 1,
                'swap' => null,
                'future' => null,
                'delivery' => null,
                'option' => null,
                'active' => 1,
                'contract' => null,
                'linear' => null,
                'inverse' => null,
                'taker' => 0.001,
                'maker' => 0.001,
                'contractSize' => null,
                'expiry' => null,
                'expiryDatetime' => null,
                'strike' => null,
                'optionType' => null,
                'precision' => [
                    'amount' => 4,
                    'price' => 6,
                    'base' => 8,
                    'quote' => 8
                ],
                'limits' => [
                    'leverage' => [
                        'min' => null,
                        'max' => null
                    ],
                    'amount' => [
                        'min' => 0.0001,
                        'max' => 100000
                    ],
                    'price' => [
                        'min' => 1.0E-6,
                        'max' => 922327
                    ],
                    'cost' => [
                        'min' => 0.0001,
                        'max' => null
                    ],
                    'market' => [
                        'min' => 0,
                        'max' => 2040.71151974
                    ]
                ],
                'info' => []
            ]
        ];

        $expected = [
            'BTC/USDT' => [
                'id' => 'BTCUSDT',
                'price_increment' => 1.0E-6,
                'amount_increment' => 0.0001
            ],
            'ETH/BTC' => [
                'id' => 'ETHBTC',
                'price_increment' => 1.0E-6,
                'amount_increment' => 0.0001
            ]
        ];

        $ccxt_exchange = $this->createMock(Exchange::class);
        $ccxt_exchange->expects($this->once())->method('fetch_markets')->willReturn($markets);

        $ccxt = new Ccxt($ccxt_exchange);

        $this->assertEquals($expected, $ccxt->getMarkets(['BTC', 'ETH', 'USDT']));
    }

    /**
     * @test
     * @throws Exception
     */
    public function it_get_markets_formatted_has_no_precisions_and_get_precisions_by_orderbook()
    {
        $markets = [
            [
                'id' => 'BTCUSDT',
                'lowercaseId' => 'btcusdt',
                'symbol' => 'BTC/USDT',
                'base' => 'BTC',
                'quote' => 'USDT',
                'settle' => null,
                'baseId' => 'BTC',
                'quoteId' => 'USDT',
                'settleId' => null,
                'type' => 'spot',
                'spot' => 1,
                'margin' => 1,
                'swap' => null,
                'future' => null,
                'delivery' => null,
                'option' => null,
                'active' => 1,
                'contract' => null,
                'linear' => null,
                'inverse' => null,
                'taker' => 0.001,
                'maker' => 0.001,
                'contractSize' => null,
                'expiry' => null,
                'expiryDatetime' => null,
                'strike' => null,
                'optionType' => null,
                'precision' => [
                    'amount' => null,
                    'price' => null,
                    'base' => null,
                    'quote' => null
                ],
                'limits' => [
                    'leverage' => [
                        'min' => null,
                        'max' => null
                    ],
                    'amount' => [
                        'min' => 0.0001,
                        'max' => 100000
                    ],
                    'price' => [
                        'min' => 1.0E-6,
                        'max' => 922327
                    ],
                    'cost' => [
                        'min' => 0.0001,
                        'max' => null
                    ],
                    'market' => [
                        'min' => 0,
                        'max' => 2040.71151974
                    ]
                ],
                'info' => []
            ]
        ];

        $expected = [
            'BTC/USDT' => [
                'id' => 'BTCUSDT',
                'price_increment' => 1.0E-6,
                'amount_increment' => 0.0001
            ]
        ];

        $orderbook = [
            'symbol' => 'BTC/USDT',
            'bids' => [
                [
                    0 => 19479.49,
                    1 => 0.01,
                ],
                [
                    0 => 19479.4,
                    1 => 0.0039,
                ],
                [
                    0 => 19479.392,
                    1 => 0.0044,
                ],
                [
                    0 => 19479.3238,
                    1 => 0.2012,
                ],
                [
                    0 => 19479.11,
                    1 => 0.2275,
                ],
            ],
            'asks' => [
                [
                    0 => 19479.5,
                    1 => 0.0117,
                ],
                [
                    0 => 19479.54,
                    1 => 0.007,
                ],
                [
                    0 => 19479.594124,
                    1 => 0.01,
                ],
                [
                    0 => 19479.6,
                    1 => 0.0099,
                ],
                [
                    0 => 19479.97,
                    1 => 1,
                ],
            ],
            'timestamp' => null,
            'datetime' => null,
            'nonce' => 25426381835,
        ];

        $ccxt_exchange = $this->getMockBuilder(Exchange::class)
            ->onlyMethods(['fetch_markets'])->addMethods(['fetch_order_book'])->getMock();

        $ccxt_exchange->expects($this->once())->method('fetch_markets')->willReturn($markets);

        $ccxt_exchange->expects($this->once())
            ->method('fetch_order_book')->with('BTC/USDT')->willReturn($orderbook);

        $ccxt = new Ccxt($ccxt_exchange);

        $this->assertEquals($expected, $ccxt->getMarkets(['BTC', 'ETH', 'USDT']));
    }

    /**
     * @test
     * @throws Exception
     */
    public function it_get_markets_formatted_has_no_precisions_and_get_precisions_with_broken_orderbook()
    {
        $markets = [
            [
                'id' => 'BTCUSDT',
                'lowercaseId' => 'btcusdt',
                'symbol' => 'BTC/USDT',
                'base' => 'BTC',
                'quote' => 'USDT',
                'settle' => null,
                'baseId' => 'BTC',
                'quoteId' => 'USDT',
                'settleId' => null,
                'type' => 'spot',
                'spot' => 1,
                'margin' => 1,
                'swap' => null,
                'future' => null,
                'delivery' => null,
                'option' => null,
                'active' => 1,
                'contract' => null,
                'linear' => null,
                'inverse' => null,
                'taker' => 0.001,
                'maker' => 0.001,
                'contractSize' => null,
                'expiry' => null,
                'expiryDatetime' => null,
                'strike' => null,
                'optionType' => null,
                'precision' => [
                    'amount' => null,
                    'price' => null,
                    'base' => null,
                    'quote' => null
                ],
                'limits' => [
                    'leverage' => [
                        'min' => null,
                        'max' => null
                    ],
                    'amount' => [
                        'min' => 0.0001,
                        'max' => 100000
                    ],
                    'price' => [
                        'min' => 1.0E-6,
                        'max' => 922327
                    ],
                    'cost' => [
                        'min' => 0.0001,
                        'max' => null
                    ],
                    'market' => [
                        'min' => 0,
                        'max' => 2040.71151974
                    ]
                ],
                'info' => []
            ]
        ];

        $ccxt_exchange = $this->getMockBuilder(Exchange::class)
            ->onlyMethods(['fetch_markets'])->addMethods(['fetch_order_book'])->getMock();

        $ccxt_exchange->expects($this->once())->method('fetch_markets')->willReturn($markets);

        $ccxt_exchange->expects($this->once())
            ->method('fetch_order_book')->with('BTC/USDT')->willReturn([]);

        $ccxt = new Ccxt($ccxt_exchange);

        $this->expectException(Exception::class);

        $ccxt->getMarkets(['BTC', 'ETH', 'USDT']);
    }
}
