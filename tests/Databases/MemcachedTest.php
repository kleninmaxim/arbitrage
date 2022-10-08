<?php

namespace Tests\Databases;

use PHPUnit\Framework\TestCase;
use Src\Databases\Memcached;

class MemcachedTest extends TestCase
{
    /** @test */
    public function test()
    {
        $data = ['one', 'two', 'three'];

        $stub = $this->createMock(Memcached::class);

        $stub->expects($this->once())
            ->method('set')
            ->with('test', $data);

        $stub->set('test', $data);
    }
}