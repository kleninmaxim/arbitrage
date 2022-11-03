<?php

use ccxt\pro\binance;
use Src\Crypto\Ccxt;
use Src\Support\Config;

require_once dirname(__DIR__, 3) . '/index.php';

$api_keys_market_discovery = Config::config('keys', 'binance', 'main');

$exchange = new binance(['apiKey' => $api_keys_market_discovery['api_public'], 'secret' => $api_keys_market_discovery['api_private'], 'enableRateLimit' => false]);

if ($exchange->has['watchBalance']) {
    $exchange::execute_and_run(function() use ($exchange) {
        // CONFIG
        $config = Config::config('arbitrage', 'first');

        $market_discovery = $config['market_discovery'];
        $assets = $config['assets'];
        // CONFIG

        // API KEYS
        $api_keys_market_discovery = Config::config('keys', $market_discovery, 'main');
        // API KEYS

        // CCXT
        $ccxt_market_discovery = Ccxt::init($market_discovery, api_key: $api_keys_market_discovery['api_public'], api_secret: $api_keys_market_discovery['api_private']);
        // CCXT

        // COUNT NECESSARY INFO
        $memcached = \Src\Databases\Memcached::init();
        $key = 'accountInfo_' . $market_discovery;
        $memcached->set($key, $ccxt_market_discovery->getBalances($assets));
        // COUNT NECESSARY INFO

        while (true) {
            try {
                // PRE COUNT
                $account_info = $memcached->get($key);
                // PRE COUNT

                $balance = yield $exchange->watch_balance();
                foreach ($balance as $key => $item)
                    if (in_array($key, $assets)) {
                        $account_info['balances']['data'][$key] = $item;
                        echo '[' . date('Y-m-d H:i:s') . '] [INFO] Balance update: ' . $key . ', free:' . $item['free'] . ', used:' . $item['used'] . ', total:' . $item['total'] . PHP_EOL;
                    }

                // END COUNTING
                $memcached->set($key, $account_info['balances']['data']);
                // END COUNTING;
            } catch (Exception $e) {
                echo get_class($e), ' ', $e->getMessage(), "\n";
                \Src\Support\Log::error($e, 'Unexpected error');
            }
        }
    });
}

