<?php

use ccxt\pro\binance;
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

        // COUNT NECESSARY INFO
        $memcached = \Src\Databases\Memcached::init();
        $key = 'account_info_' . $market_discovery;
        // COUNT NECESSARY INFO

        while (true) {
            try {
                // PRE COUNT
                $account_info = $memcached->get($key);
                // PRE COUNT

                $balance = yield $exchange->watch_balance();
                foreach ($balance as $key => $item)
                    if (in_array($key, $assets))
                        $account_info['balances']['data'][$key] = $item;

                // END COUNTING
                $memcached->set($key, $account_info['balances']['data']);
                // END COUNTING
                print_r($balance);
                echo PHP_EOL;
            } catch (Exception $e) {
                echo get_class($e), ' ', $e->getMessage(), "\n";
                \Src\Support\Log::error($e, 'Unexpected error');
            }
        }
    });
}


