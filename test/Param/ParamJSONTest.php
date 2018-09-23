<?php

namespace rain1\PDOPowered\Param\test;

use PHPUnit\Framework\TestCase;
use rain1\PDOPowered\Param\ParamJSON;

class ParamJSONTest extends TestCase
{
    public function testStandard()
    {
        $param = new ParamJSON(["hello", "world"]);
        self::assertEquals(json_encode(["hello", "world"]), $param->getValue());
        self::assertEquals([\PDO::PARAM_STR], $param->getArguments());
    }

    public function testString()
    {
        $param = new ParamJSON('Hello world');
        self::assertEquals('"Hello world"', $param->getValue());

        $param = new ParamJSON('{}');
        self::assertEquals('"{}"', $param->getValue());
    }

}