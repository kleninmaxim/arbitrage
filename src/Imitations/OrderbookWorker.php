<?php

namespace Src\Imitations;

class OrderbookWorker
{
    public static function init(): static
    {
        return new static();
    }

    public function getOrderbook(...$parameters): mixed
    {
        return [
            'binance_BTC/USDT' => [
                'data' => [
                    'service' => 'BINANCE BTC/USDT, ETH/USDT',
                    'orderbook' => [
                        'symbol' => 'BTC/USDT',
                        'bids' => [
                            [
                                0 => 20000,
                                1 => 1,
                            ],
                            [
                                0 => 19000,
                                1 => 2,
                            ],
                            [
                                0 => 18000,
                                1 => 3,
                            ]
                        ],
                        'asks' => [
                            [
                                0 => 21000,
                                1 => 1,
                            ],
                            [
                                0 => 22000,
                                1 => 2,
                            ],
                            [
                                0 => 23000,
                                1 => 3,
                            ]
                        ],
                        'timestamp' => null,
                        'datetime' => null,
                        'exchange' => 'binance',
                        'nonce' => 25426381835,
                    ]
                ],
                'timestamp' => microtime(true)
            ],
            'exmo_BTC/USDT' => [
                'data' => [
                    'service' => 'EXMO BTC/USDT, ETH/USDT',
                    'orderbook' => [
                        'symbol' => 'BTC/USDT',
                        'bids' => [
                            [
                                0 => 19900,
                                1 => 0.6,
                            ],
                            [
                                0 => 19000,
                                1 => 1,
                            ],
                            [
                                0 => 18000,
                                1 => 2,
                            ]
                        ],
                        'asks' => [
                            [
                                0 => 21100,
                                1 => 0.6,
                            ],
                            [
                                0 => 22000,
                                1 => 1,
                            ],
                            [
                                0 => 23000,
                                1 => 2,
                            ]
                        ],
                        'timestamp' => null,
                        'datetime' => null,
                        'exchange' => 'exmo',
                        'nonce' => 25426381835,
                    ]
                ],
                'timestamp' => microtime(true)
            ],
            'binance_ETH/USDT' => [
                'data' => [
                    'service' => 'BINANCE BTC/USDT, ETH/USDT',
                    'orderbook' => [
                        'symbol' => 'ETH/USDT',
                        'bids' => [
                            [
                                0 => 1400,
                                1 => 3,
                            ],
                            [
                                0 => 1350,
                                1 => 4,
                            ],
                            [
                                0 => 1300,
                                1 => 4,
                            ]
                        ],
                        'asks' => [
                            [
                                0 => 1450,
                                1 => 3,
                            ],
                            [
                                0 => 1500,
                                1 => 3,
                            ],
                            [
                                0 => 1510,
                                1 => 4,
                            ]
                        ],
                        'timestamp' => null,
                        'datetime' => null,
                        'exchange' => 'binance',
                        'nonce' => 25426381835,
                    ]
                ],
                'timestamp' => microtime(true)
            ],
            'exmo_ETH/USDT' => [
                'data' => [
                    'service' => 'EXMO BTC/USDT, ETH/USDT',
                    'orderbook' => [
                        'symbol' => 'ETH/USDT',
                        'bids' => [
                            [
                                0 => 1380,
                                1 => 2,
                            ],
                            [
                                0 => 1350,
                                1 => 3,
                            ],
                            [
                                0 => 1300,
                                1 => 4,
                            ]
                        ],
                        'asks' => [
                            [
                                0 => 1470,
                                1 => 2,
                            ],
                            [
                                0 => 1500,
                                1 => 4,
                            ],
                            [
                                0 => 1510,
                                1 => 2,
                            ]
                        ],
                        'timestamp' => null,
                        'datetime' => null,
                        'exchange' => 'exmo',
                        'nonce' => 25426381835,
                    ]
                ],
                'timestamp' => microtime(true)
            ]
        ];
    }
}