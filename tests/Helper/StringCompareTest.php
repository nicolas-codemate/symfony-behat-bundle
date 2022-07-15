<?php

namespace Helper;

use Elbformat\SymfonyBehatBundle\Helper\StringCompare;
use PHPUnit\Framework\TestCase;

class StringCompareTest extends TestCase
{
    protected StringCompare $comp;

    protected function setUp(): void
    {
        $this->comp = new StringCompare();
    }

    /** @dataProvider stringContainsProvider */
    public function testStringContains(string $haystack, string $needle): void
    {
        $this->assertTrue($this->comp->stringContains($haystack, $needle));
    }

    public function stringContainsProvider()
    {
        return [
            ['Hello World', 'Hello'],
            ['Hello World', 'World'],
            ['Hello World', 'o W'],
            ['Hello World', '~.*'],
            ['Hello World', '~ello'],
            ['Hello World', '~[a-z]+'],
        ];
    }

    /**
     * @dataProvider stringContainsNotProvider
     */
    public function testStringContainsNot(string $haystack, string $needle)
    {
        $this->assertFalse($this->comp->stringContains($haystack, $needle));
    }

    public function stringContainsNotProvider()
    {
        return [
            ['Hello','World'],
            ['Hello','hello'],
            ['Hello','~[0-9]'],
        ];
    }
}
