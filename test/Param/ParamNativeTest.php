<?php

namespace rain1\PDOPowered\Param\test;

use PHPUnit\Framework\TestCase;
use rain1\PDOPowered\Param\ParamString;

class ParamNativeTest extends TestCase
{
    public function testString()
    {
        $param = new ParamString("Hello");
        self::assertEquals("Hello", $param->getValue());
        self::assertEquals([\PDO::PARAM_STR], $param->getArguments());
    }
}