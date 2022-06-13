<?php

namespace Elbformat\SymfonyBehatBundle\Browser;

class StateFactory
{
    public function newState(): State
    {
        return new State();
    }
}
