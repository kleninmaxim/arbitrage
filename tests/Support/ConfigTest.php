<?php

namespace Tests\Support;

use PHPUnit\Framework\TestCase;
use Src\Support\Config;

class ConfigTest extends TestCase
{

    public function setUp(): void
    {
        $this->config_directory = dirname(__DIR__, 2) . '/config/';

        $now = time();

        $this->configs = [
            'default' => [
                'file_name' =>  $now . '_default',
                'content' => <<<EOT
                    <?php
                    
                    return [
                        'timezone' => 'UTC',
                    
                        'mysql' => 'localhost',
                    
                        'okx' => 'main'
                    ];
                    EOT
            ],
            'db' => [
                'file_name' => $now . '_db',
                'content' => <<<EOT
                    <?php

                    return [
                        'mysql' => [
                            'localhost' => [
                                'host' => '127.0.0.1',
                                'port' => '3306'
                            ],
                            'server' => [
                                'host' => '0.0.0.0',
                                'port' => '3306'
                            ]
                        ]
                    ];
                    EOT
            ],
            'keys' => [
                'file_name' => $now . '_keys',
                'content' => <<<EOT
                    <?php

                    return [
                        'okx' => [
                            'demo' => [
                                'api' => '123',
                                'debug' => true
                            ],
                            'main' => [
                                'api' => '234',
                                'debug' => false
                            ]
                        ],
                        'binance' => [
                            'main' => [
                                'api' => '098',
                                'debug' => false
                            ]
                        ]
                    ];
                    EOT
            ]
        ];

        foreach ($this->configs as $key => $config)
            $this->configs[$key]['full_path'] = $this->config_directory . $config['file_name'] . '.config.php';

        foreach ($this->configs as $config)
            file_put_contents(
                $config['full_path'],
                $config['content']
            );

        Config::initPath($this->config_directory);
    }

    public function tearDown(): void
    {
        foreach ($this->configs as $config)
            @unlink($config['full_path']);

        parent::tearDown();
    }

    /** @test */
    public function proof_configuration()
    {
        $this->assertEquals('app', Config::getDefaultSettingsFile());

        Config::changeDefaultSettingsFile($this->configs['default']['file_name']);

        $this->assertEquals($this->configs['default']['file_name'], Config::getDefaultSettingsFile());

        $this->assertEquals(
            ['timezone' => 'UTC', 'mysql' => 'localhost', 'okx' => 'main'],
            Config::get($this->configs['default']['file_name'])
        );

        $this->assertEquals(
            ['mysql' => ['localhost' => ['host' => '127.0.0.1', 'port' => '3306'], 'server' => ['host' => '0.0.0.0', 'port' => '3306']]],
            Config::get($this->configs['db']['file_name'])
        );

        $this->assertEquals(
            ['okx' => ['demo' => ['api' => '123', 'debug' => true], 'main' => ['api' => '234', 'debug' => false]], 'binance' => ['main' => ['api' => '098', 'debug' => false]]],
            Config::get($this->configs['keys']['file_name'])
        );

        $this->assertEquals(
            ['host' => '127.0.0.1', 'port' => '3306'],
            Config::file($this->configs['db']['file_name'], 'mysql')
        );

        $this->assertEquals(
            ['api' => '234', 'debug' => false],
            Config::file($this->configs['keys']['file_name'], 'okx')
        );

        $this->assertEquals(
            ['api' => '123', 'debug' => true],
            Config::file($this->configs['keys']['file_name'], 'okx', 'demo')
        );

        $this->assertEquals(
            ['api' => '098', 'debug' => false],
            Config::file($this->configs['keys']['file_name'], 'binance', 'main')
        );

        $this->assertEquals(
            'UTC',
            Config::config($this->configs['default']['file_name'], 'timezone')
        );

        $this->assertEquals(
            ['localhost' => ['host' => '127.0.0.1', 'port' => '3306'], 'server' => ['host' => '0.0.0.0', 'port' => '3306']],
            Config::config($this->configs['db']['file_name'], 'mysql')
        );

        $this->assertEquals(
            ['api' => '234', 'debug' => false],
            Config::config($this->configs['keys']['file_name'], 'okx', 'main')
        );
    }
}
