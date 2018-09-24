<?php

namespace rain1\PDOPowered\Config\test;

use PHPUnit\Framework\TestCase;
use rain1\PDOPowered\Config\DSN;

class DSNTest extends TestCase
{
    public function testConstructor()
    {
        $config = new DSN("mysql:host=localhost;port=3306;dbname=dbname;charset=utf8", "user", "password");
        $config->setOptions([\PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES \'UTF8\'']);
        self::assertEquals("mysql:host=localhost;port=3306;dbname=dbname;charset=utf8", $config->getConnectionString());
        self::assertEquals("user", $config->getUser());
        self::assertEquals("password", $config->getPassword());
        self::assertEquals([\PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES \'UTF8\''], $config->getOptions());
    }

}