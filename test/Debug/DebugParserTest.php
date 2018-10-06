<?php

namespace rain1\PDOPowered\Param\test;

use PHPUnit\Framework\TestCase;
use rain1\PDOPowered\Debug\DebugParser;
use rain1\PDOPowered\Param\ParamInt;
use rain1\PDOPowered\Param\ParamString;

class DebugParserTest extends TestCase
{
    public function testSimpleQuery()
    {
        $debugHelper = new DebugParser();

        $result = $debugHelper->parseDebugInfo("query", new \PDOStatement(), "QUERY STRING ? ?", [1, "2"]);

        self::assertTrue(is_array($result));
        self::assertArrayHasKey('query', $result);
        self::assertArrayHasKey('original', $result['query']);
        self::assertArrayHasKey('params', $result['query']);
        self::assertArrayHasKey('executionTime', $result['query']);
        self::assertArrayHasKey('demo', $result['query']);
        self::assertEquals('QUERY STRING ? ?', $result['query']['original']);
        self::assertEquals('QUERY STRING 1 \'2\'', $result['query']['demo']);
    }

    public function testParamQuery()
    {
        $debugHelper = new DebugParser();

        $result = $debugHelper->parseDebugInfo("query", new \PDOStatement(),
            "QUERY STRING :var1 :var2",
            [
                'var1' => 1,
                'var2' => "2"
            ]);
        self::assertEquals('QUERY STRING 1 \'2\'', $result['query']['demo']);
    }

    public function testAdvancedQuery()
    {
        $debugHelper = new DebugParser();

        $result = $debugHelper->parseDebugInfo("query", new \PDOStatement(), "QUERY STRING \"?\" ? ?", [1, "2"]);

        self::assertEquals('QUERY STRING "?" ? ?', $result['query']['original']);
        self::assertEquals('QUERY STRING "?" 1 \'2\'', $result['query']['demo']);

        $result = $debugHelper->parseDebugInfo("query", new \PDOStatement(), 'QUERY "?" AND "?\"" ? ?', [1, "2"]);

        self::assertEquals('QUERY "?" AND "?\"" ? ?', $result['query']['original']);
        self::assertEquals('QUERY "?" AND "?\"" 1 \'2\'', $result['query']['demo']);
    }

    public function testAposQuery()
    {
        $debugHelper = new DebugParser();

        $result = $debugHelper->parseDebugInfo("query", new \PDOStatement(), "QUERY STRING \"?\" ? ?", [1, "2"]);

        self::assertEquals('QUERY STRING "?" ? ?', $result['query']['original']);
        self::assertEquals('QUERY STRING "?" 1 \'2\'', $result['query']['demo']);

        $result = $debugHelper->parseDebugInfo("query", new \PDOStatement(), 'QUERY \'?\' AND "?\"" ? ?', [1, "2"]);

        self::assertEquals('QUERY \'?\' AND "?\"" ? ?', $result['query']['original']);
        self::assertEquals('QUERY \'?\' AND "?\"" 1 \'2\'', $result['query']['demo']);
    }

    public function testCustomObjectAsParam()
    {
        $debugHelper = new DebugParser();

        $result = $debugHelper->parseDebugInfo("query", new \PDOStatement(), "QUERY STRING ? ?", [1, new class
        {
            public function __toString()
            {
                return "Hello";
            }
        }]);

        self::assertEquals('QUERY STRING ? ?', $result['query']['original']);
        self::assertEquals('QUERY STRING 1 \'Hello\'', $result['query']['demo']);

    }

    public function testParamDebug()
    {
        $debugHelper = new DebugParser();

        $result = $debugHelper->parseDebugInfo("query", new \PDOStatement(),
            "QUERY STRING ? ?",
            [new ParamInt(1), new ParamString(2)]);
        self::assertEquals('QUERY STRING ? ?', $result['query']['original']);
        self::assertEquals('QUERY STRING 1 \'2\'', $result['query']['demo']);
    }

}