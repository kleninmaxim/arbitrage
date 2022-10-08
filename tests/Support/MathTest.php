<?php

namespace Tests\Support;

use PHPUnit\Framework\TestCase;
use Src\Support\Math;

class MathTest extends TestCase
{

    /**
     * @test
     * @dataProvider incrementNumbers
     */
    public function it_increment_number_check($expected, $real, $increment)
    {
        $this->assertEquals(
            $expected,
            Math::incrementNumber($real, $increment)
        );
    }

    public function incrementNumbers(): array
    {
        return [
            [0.21, 0.214, 0.01],
            [532531, 532531.231, 1],
            [123.6, 123.77, 0.3],
            [0.9412132, 0.9412132421235321, 0.0000001]
        ];
    }

    /**
     * @test
     * @dataProvider floatNumbers
     */
    public function it_compare_float_numbers_check($expected, $a, $b)
    {
        $this->assertEquals(
            $expected,
            Math::compareFloats($a, $b)
        );
    }

    public function floatNumbers(): array
    {
        return [
            [true, 0.214, 1 - 0.786],
            [true, 2.2241, 1 + 1.2241],
            [false, 2, 2 + 0.00000001],
            [true, 2, 2 + 0.00000000001],
            [true, 123139, 123139.2312 - 0.2312],
            [true, 0.000001, 1.0E-6],
            [true, 422312.0001, 421312 + (1.0E-4) + (1.0E+3)]
        ];
    }
}
