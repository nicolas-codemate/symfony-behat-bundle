<?php

namespace Elbformat\SymfonyBehatBundle\Tests\Helper;

use Elbformat\SymfonyBehatBundle\Helper\ArrayDeepCompare;
use PHPUnit\Framework\TestCase;

class ArrayDeepCompareTest extends TestCase
{
    protected ArrayDeepCompare $comp;

    protected function setUp(): void
    {
        $this->comp = new ArrayDeepCompare();
    }

    /** @dataProvider arrayContainsProvider */
    public function testArrayContains(array $container, array $containment): void
    {
        $this->assertTrue($this->comp->arrayContains($container, $containment));
    }

    public function arrayContainsProvider()
    {
        return [
            [['a', 'b'], /* contains */ ['a']],
            [['a', 'b'], /* contains */ ['b']],
            [['a' => 'b', 'c' => 'd'], /* contains */ ['a' => 'b']],
            [['a' => 'b', 'c' => 'd'], /* contains */ ['c' => 'd']],
            [['a' => 'b', 'c' => 'd'], /* contains */ ['c' => 'd']],
            [['a' => [['b' => 'c', 'd' => 'e']]], /* contains */ ['a' => [['b' => 'c']]]],
            [['a' => [['b' => 'c', 'd' => 'e']]], /* contains */ ['a' => [['d' => 'e']]]],
        ];
    }

    /**
     * @dataProvider arrayContainsNotProvider
     */
    public function testArrayContainsNot(array $container, array $containment, string $difference)
    {
        $this->assertFalse($this->comp->arrayContains($container, $containment));
        $this->assertEquals($difference, $this->comp->getDifference());
    }

    public function arrayContainsNotProvider()
    {
        return [
            [['a', 'b'], /* doesn't contain */ ['c'], /* because */ '0: c Missing'],
            [['a' => 'b', 'c' => 'd'], /* doesn't contain */  ['a' => 'c'], /* because */ 'a: (string) c != (string) b'],
            [['a' => 'b', 'c' => 'd'], /* doesn't contain */  ['c' => 'b'], /* because */ 'c: (string) b != (string) d'],
            [['a' => [['b' => 'c', 'd' => 'e']]], /* doesn't contain */  ['a' => ['b' => 3]], /* because */ 'a.b: Missing'],
            [['a' => [['b' => 'c', 'd' => 'e']]], /* doesn't contain */  ['a' => ['d' => 'c']], /* because */ 'a.d: Missing'],
        ];
    }
}
