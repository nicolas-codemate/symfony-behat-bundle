<?php

namespace Elbformat\SymfonyBehatBundle\Tests\Browser;

use Elbformat\SymfonyBehatBundle\Browser\State;
use Elbformat\SymfonyBehatBundle\Browser\StateFactory;
use PHPUnit\Framework\TestCase;

class StateFactoryTest extends TestCase
{
    public function testNewState(): void
    {
        $factory = new StateFactory();
        $state = $factory->newState();
        $this->assertInstanceOf(State::class, $state);
    }
}
