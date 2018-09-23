<?php

namespace rain1\PDOPowered\Param\test;

use PHPUnit\Framework\TestCase;
use rain1\PDOPowered\Param\ParamNative;

class ParamStringTest extends TestCase
{
    public function testString()
    {
        $param = new ParamNative("Hello", \PDO::PARAM_STR);
        self::assertEquals("Hello", $param->getValue());
        self::assertEquals([\PDO::PARAM_STR], $param->getArguments());

        $param = new ParamNative("1", \PDO::PARAM_INT);
        self::assertEquals("1", $param->getValue());
        self::assertEquals([\PDO::PARAM_INT], $param->getArguments());
    }
}