<?php

namespace Tests\Databases;

use PHPUnit\Framework\TestCase;

class Memcached extends TestCase
{
    /** @test */
    public function test()
    {
        $data = ['one', 'two', 'three'];

        $stub = $this->createMock(\Src\Databases\Memcached::class);

        $stub->expects($this->once())
            ->method('set')
            ->with('test', $data);

        $stub->set('test', $data);
    }
}