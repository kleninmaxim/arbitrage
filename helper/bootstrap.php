<?php

require dirname(__DIR__) . "/vendor/autoload.php";


require_once dirname(__DIR__) . '/config/main.config.php';

require_once dirname(__DIR__) . '/config/db.config.php';


require_once dirname(__DIR__) . '/app/Arbitrage.php';

require_once dirname(__DIR__) . '/app/Websocket.php';

require_once dirname(__DIR__) . '/app/Start.php';


require_once dirname(__DIR__) . '/src/Rate.php';

require_once dirname(__DIR__) . '/src/Cache.php';

require_once dirname(__DIR__) . '/src/Ccxt.php';

require_once dirname(__DIR__) . '/src/DB.php';

require_once dirname(__DIR__) . '/src/Act.php';

require_once dirname(__DIR__) . '/src/PriceMaker.php';

require_once dirname(__DIR__) . '/src/Orderbook.php';

require_once dirname(__DIR__) . '/src/Result.php';

require_once dirname(__DIR__) . '/src/Math.php';

\src\DB::connect();
