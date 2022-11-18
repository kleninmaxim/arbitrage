<?php

use Src\Support\Config;
use Src\Support\Log;

require __DIR__ . '/vendor/autoload.php';

const CONFIG = __DIR__ . '/config/';

const START = __DIR__ . '/start/';

const CACHE = __DIR__ . '/storage/cache/';

const LOGS = __DIR__ . '/storage/logs/';

Config::initPath(CONFIG);
Log::initPath(LOGS);

date_default_timezone_set(Config::config('app', 'timezone'));
