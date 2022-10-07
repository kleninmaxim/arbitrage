<?php

namespace Tests\Databases;

use PHPUnit\Framework\TestCase;

class Memcached extends TestCase
{
    /*
     * This test need Memcached to be started
     * sudo /etc/init.d/memcached restart
     * sudo systemctl restart memcached
     */
    public function setUp(): void
    {
        $this->memcached = new \Src\Databases\Memcached();

        $this->memcached->flushAll();
    }

    public function tearDown(): void
    {
        $this->memcached->flushAll();

        parent::tearDown();
    }

    /** @test */
    public function set_one_key_and_get()
    {
        $this->memcached->set('test', ['one', 'two', 'three']);

        $data = $this->memcached->get('test');

        $this->assertEquals(['one', 'two', 'three'], $data['data']);
        $this->assertIsFloat($data['timestamp']);
    }

    /** @test */
    public function set_keys_and_get_all_with_prefix()
    {
        $this->memcached->setPrefix('prefix');

        $this->memcached->set('test_one', ['one', 'two', 'three']);
        $this->memcached->set('test_two', ['four', 'five', 'six']);
        $this->memcached->set('test_three', 'seven');

        $data = $this->memcached->get(['test_one', 'test_two', 'test_three']);

        $this->assertEquals(['one', 'two', 'three'], $data['prefix_test_one']['data']);
        $this->assertEquals(['four', 'five', 'six'], $data['prefix_test_two']['data']);
        $this->assertEquals('seven', $data['prefix_test_three']['data']);
        $this->assertIsFloat($data['prefix_test_one']['timestamp']);

        $this->memcached->setPrefix('');

        $data = $this->memcached->get(['test_one', 'test_two', 'test_three']);

        $this->assertEmpty($data);
    }
}