<?php

namespace Tests\Support;

use Exception;
use Src\Support\Log;
use PHPUnit\Framework\TestCase;

class LogTest extends TestCase
{
    public function setUp(): void
    {
        $this->log_file_name = time() . '_test';

        $this->config_directory = dirname(__DIR__, 2) . '/storage/logs/';

        $this->log_file_full_name = $this->config_directory . $this->log_file_name  . '.log';

        Log::initPath($this->config_directory);
    }

    public function tearDown(): void
    {
        Log::$debug = false;

        @unlink($this->log_file_full_name);

        parent::tearDown();
    }

    /** @test */
    public function it_log_check()
    {
        Log::log($this->log_file_name, 'This is a mistake');
        Log::log($this->log_file_name, ['test', 'one', 'two']);

        $this->assertFileExists($this->log_file_full_name);

        $content = file_get_contents($this->log_file_full_name);

        $this->assertStringContainsString('] This is a mistake', $content);
        $this->assertStringContainsString('------------------------------------------------------------------------------------------------------------------------', $content);
        $this->assertStringContainsString('test', $content);
        $this->assertStringContainsString('one', $content);
        $this->assertStringContainsString('two', $content);
    }

    /** @test */
    public function it_debug_check()
    {
        $debug_file = $this->config_directory . '/debug' . '.log';

        @unlink($debug_file);

        Log::debug('This is debug info');

        $this->assertFileDoesNotExist($debug_file);

        Log::$debug = true;

        Log::debug('This is debug info');
        Log::debug(['test', 'one', 'two']);

        $this->assertFileExists($debug_file);

        $content = file_get_contents($debug_file);

        $this->assertStringContainsString('] This is debug info', $content);
        $this->assertStringContainsString('------------------------------------------------------------------------------------------------------------------------', $content);
        $this->assertStringContainsString('test', $content);
        $this->assertStringContainsString('one', $content);
        $this->assertStringContainsString('two', $content);

        @unlink($debug_file);
    }

    /** @test */
    public function it_error_check()
    {
        $debug_file = $this->config_directory . '/error' . '.log';

        @unlink($debug_file);

        $this->assertFileDoesNotExist($debug_file);

        try {
            throw new Exception();
        } catch (Exception $e) {
            Log::error($e);

            $this->assertFileExists($debug_file);

            $content = file_get_contents($debug_file);

            $this->assertStringContainsString('./tests/Support/LogTest.php', $content);

            @unlink($debug_file);

            Log::error($e, ['$var' => ['test' => [], 'one', 'tow' => ['has', 'am', 'are']]]);

            $content = file_get_contents($debug_file);

            $this->assertStringContainsString('./tests/Support/LogTest.php', $content);

            $this->assertStringContainsString('[$var]', $content);
            $this->assertStringContainsString('[test] => Array', $content);
            $this->assertStringContainsString('[0] => one', $content);
            $this->assertStringContainsString('[tow] => Array', $content);
            $this->assertStringContainsString('[0] => has', $content);
            $this->assertStringContainsString('[1] => am', $content);

            @unlink($debug_file);

            return;
        }
    }
}