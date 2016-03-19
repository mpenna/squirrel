<?php

use Laravel\Cache\Squirrel;
use Laravel\Cache\SquirrelConfig;
use Laravel\Cache\SquirrelQueryBuilder;
use Laravel\Cache\SquirrelQueryParser;

use Mockery as m;

class SquirrelTests extends PHPUnit_Framework_TestCase
{
    public function tearDown()
    {
        m::close();
    }
}
