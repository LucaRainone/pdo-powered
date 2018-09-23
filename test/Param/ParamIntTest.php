<?php

namespace rain1\PDOPowered\Param\test;

use PHPUnit\Framework\TestCase;
use rain1\PDOPowered\Param\ParamInt;

class ParamIntTest extends TestCase
{
    public function testValue()
    {
        $param = new ParamInt(3);
        self::assertEquals(3, $param->getValue());
        self::assertEquals([\PDO::PARAM_INT], $param->getArguments());
    }

    public function testCast()
    {
        $param = new ParamInt("3");
        self::assertEquals(3, $param->getValue());
    }
}