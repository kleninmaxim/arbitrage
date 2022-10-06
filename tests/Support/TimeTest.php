<?php

namespace Tests\Support;

use PHPUnit\Framework\TestCase;
use Src\Support\Time;

class TimeTest extends TestCase
{
    public function tearDown(): void
    {
        Time::reset();

        parent::tearDown();
    }

    /** @test */
    public function it_has_static_attribute()
    {
        $this->assertClassHasStaticAttribute('start', Time::class);
    }

    /** @test */
    public function it_return_first_time_false_and_next_false_and_next_true()
    {
        $time = 0.01;

        $this->assertFalse(Time::up($time, 'test'));

        $this->assertFalse(Time::up($time, 'test'));

        $this->usleep($time);

        $this->assertTrue(Time::up($time, 'test'));
    }

    /** @test */
    public function it_return_first_time_true_and_next_false_and_next_true()
    {
        $time = 0.01;

        $this->assertTrue(Time::up($time, 'test', true));

        $this->assertFalse(Time::up($time, 'test'));

        $this->usleep($time);

        $this->assertTrue(Time::up($time, 'test'));
    }

    /** @test */
    public function use_two_keys()
    {
        $time = 0.01;

        $this->assertTrue(Time::up($time, 'test_one', true));
        $this->assertFalse(Time::up($time, 'test_two'));

        $this->usleep($time);

        $this->assertTrue(Time::up($time, 'test_one'));
        $this->assertTrue(Time::up($time, 'test_two'));
    }

    /** @test */
    public function use_two_keys_and_update()
    {
        $time = 0.01;

        $this->assertTrue(Time::up($time, 'test_one', true));
        $this->assertFalse(Time::up($time, 'test_two'));

        $this->usleep($time);

        $this->assertTrue(Time::up($time, 'test_one'));

        Time::update();

        $this->assertFalse(Time::up($time, 'test_two'));
    }

    /** @test */
    public function use_three_keys()
    {
        $time = 0.01;

        $this->assertTrue(Time::up($time, 'test_one', true));
        $this->assertFalse(Time::up($time, 'test_two'));
        $this->assertFalse(Time::up($time, 'test_three'));

        $this->assertFalse(Time::up($time, 'test_one', true));
        $this->assertFalse(Time::up($time, 'test_two'));
        $this->assertFalse(Time::up($time, 'test_three'));

        $this->usleep($time);

        $this->assertTrue(Time::up($time, 'test_one'));
        $this->assertTrue(Time::up($time, 'test_two'));

        $this->usleep($time);

        $this->assertTrue(Time::up($time, 'test_three'));
    }

    /** @test */
    public function big_circles()
    {
        $time = 0.01;

        $this->assertTrue(Time::up($time, 'test_one', true));
        $this->assertFalse(Time::up($time, 'test_two'));
        $this->assertFalse(Time::up($time, 'test_three'));
        $this->assertFalse(Time::up($time, 'test_four'));

        $this->usleep($time);

        $this->assertFalse(Time::up($time, 'test_one', true));
        $this->assertTrue(Time::up($time, 'test_two'));
        $this->assertTrue(Time::up($time, 'test_three'));
        $this->assertTrue(Time::up($time, 'test_four'));

        $this->usleep($time);

        $this->assertTrue(Time::up($time, 'test_one', true));
        $this->assertFalse(Time::up($time, 'test_two'));
        $this->assertFalse(Time::up($time, 'test_three'));
        $this->assertFalse(Time::up($time, 'test_four'));

        Time::update($time);

        $this->assertFalse(Time::up($time, 'test_one', true));
        $this->assertFalse(Time::up($time, 'test_two'));
        $this->assertFalse(Time::up($time, 'test_three'));
        $this->assertFalse(Time::up($time, 'test_four'));

        $this->usleep($time);

        Time::update($time);

        $this->assertTrue(Time::up($time, 'test_one', true));
        $this->assertFalse(Time::up($time, 'test_two'));
        $this->assertFalse(Time::up($time, 'test_three'));
        $this->assertFalse(Time::up($time, 'test_four'));

        $this->usleep($time);

        $this->assertFalse(Time::up($time, 'test_one', true));
        $this->assertTrue(Time::up($time, 'test_two'));
        $this->assertTrue(Time::up($time, 'test_three'));
        $this->assertTrue(Time::up($time, 'test_four'));

        Time::update($time);

        $this->usleep($time);

        Time::update($time);

        $this->assertTrue(Time::up($time, 'test_one', true));
        $this->assertFalse(Time::up($time, 'test_two'));
        $this->assertFalse(Time::up($time, 'test_three'));
        $this->assertFalse(Time::up($time, 'test_four'));

        $this->usleep($time);

        $this->assertFalse(Time::up($time, 'test_one', true));
        $this->assertTrue(Time::up($time, 'test_two'));
        $this->assertTrue(Time::up($time, 'test_three'));
        $this->assertTrue(Time::up($time, 'test_four'));

        Time::update(3 * $time);

        $this->assertTrue(Time::up($time, 'test_one', true));
        $this->assertFalse(Time::up($time, 'test_two'));
        $this->assertFalse(Time::up($time, 'test_three'));
        $this->assertFalse(Time::up($time, 'test_four'));

        $this->usleep($time);

        Time::update(3 * $time);

        $this->assertFalse(Time::up($time, 'test_one', true));
        $this->assertTrue(Time::up($time, 'test_two'));
        $this->assertTrue(Time::up($time, 'test_three'));
        $this->assertTrue(Time::up($time, 'test_four'));
    }

    public function usleep(float $time)
    {
        usleep(1000000 * ($time + 0.01));
    }
}