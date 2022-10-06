<?php

$folder = dirname(__DIR__) . '/config/';

$files = [
    [
        'name' => 'app',
        'content' => <<<EOT
            <?php
            
            return [
                'timezone' => 'UTC',
            
                'mysql' => 'localhost',
            
                'okx' => 'demo'
            ];
            EOT,
    ]
];

foreach ($files as $file) {
    $full_file_path = $folder . $file['name'] . '.config.php';

    if (!file_exists($full_file_path)) {
        file_put_contents(
            $full_file_path,
            $file['content']
        );

        echo '[' . date('Y-m-d H:i:s') . '] Create file ' . $file['name'] . PHP_EOL;
    } else
        echo '[' . date('Y-m-d H:i:s') . '] File already exist: ' . $file['name'] . PHP_EOL;
}