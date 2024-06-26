<?php

namespace rain1\PDOPowered\test;

use PHPUnit\Framework\TestCase;
use rain1\PDOPowered\Config\Config;
use rain1\PDOPowered\Debug\DebugParser;
use rain1\PDOPowered\Exception;
use rain1\PDOPowered\Expression;
use rain1\PDOPowered\Param\ParamInt;
use rain1\PDOPowered\Param\ParamJSON;
use rain1\PDOPowered\Param\ParamString;
use rain1\PDOPowered\PDOPowered;

class PDOPoweredTest extends TestCase
{

    private ?PDOPowered $db;

    private static function assertObjectHasAttribute(string $prop, mixed $obj): void
    {
        self::assertTrue(property_exists($obj, $prop));
    }

    public function testPhpUnitXMlDist()
    {
        self::assertArrayHasKey("DB_USER", $GLOBALS, "rename phpunit.dist.xml in phpunit.xml and/or do the right thing");
    }

    /**
     * @throws \Exception
     */
    public function testNativePrepare()
    {
        $db = $this->getDbInstance();
        $statement = $db->prepare("SELECT * FROM tabletest");
        self::assertInstanceOf(\PDOStatement::class, $statement);
    }

    private function getDbInstance(): PDOPowered
    {

        if (!isset($this->db))
            $this->db = $this->getInstance();

        return $this->db;

    }

    private function getInstance(): PDOPowered
    {
        $config = new Config(
            "mysql",
            $GLOBALS['DB_USER'],
            $GLOBALS['DB_PASSWD'],
            $GLOBALS['DB_HOST'],
            $GLOBALS['DB_PORT'],
            "",
            "utf8",
            [\PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES \'UTF8\'']
        );
        return new PDOPowered($config);
    }

    /**
     * @throws Exception
     */
    private function _testSimpleQuery(PDOPowered $db)
    {
        $row = $db->query("SELECT 1 as const")->fetch();

        self::assertTrue($db->isConnected(), "Database should be connected at this point");

        self::assertArrayHasKey("const", $row, "default resultset is ASSOC");

    }

    public function testInstanceWithPDO()
    {
        $pdo = new \PDO("mysql:host={$GLOBALS['DB_HOST']};port={$GLOBALS['DB_PORT']};charset=utf8", $GLOBALS['DB_USER'], $GLOBALS['DB_PASSWD']);

        $db = PDOPowered::buildFromPDOInstance($pdo);
        $this->_testSimpleQuery($db);

    }

    /**
     * @throws Exception
     */
    public function testSimpleQuery()
    {

        $db = $this->getDbInstance();

        $this->_testSimpleQuery($db);
        $row = $db->query("SELECT 1 as const")->fetch();
        self::assertArrayNotHasKey("0", $row, "default resultset should be ASSOC");

    }

    /**
     * @throws Exception
     */
    public function testSyntaxErrorQueryThrowsException()
    {
        $this->expectException(\PDOException::class);
        $db = $this->getDbInstance();
        $db->query("SELEKT 1 as const");

    }

    /**
     * @throws Exception
     */
    public function testQuestionMarkParam()
    {
        $db = $this->getDbInstance();

        $row = $db->query("SELECT ? as col", [1])->fetch();

        self::assertEquals("1", $row['col']);
    }

    /**
     * @throws Exception
     */
    public function testCustomParam()
    {
        $db = $this->getDbInstance();

        $col = $db->query("SELECT ? as col", [new ParamString("Hello")])->fetchColumn();
        self::assertEquals("Hello", $col);

        $col = $db->query("SELECT :myParam as col", ['myParam' => new ParamString("Hello")])->fetchColumn();
        self::assertEquals("Hello", $col);
    }

    /**
     * @throws Exception
     */
    public function testNamedParam()
    {
        $db = $this->getDbInstance();

        $row = $db->query("SELECT :var as col", ['var' => "myString"])->fetch();

        self::assertEquals("myString", $row['col']);
    }

    /**
     * @throws Exception
     */
    public function testMultipleQuestionMarkParam()
    {
        $db = $this->getDbInstance();

        $row = $db->query("SELECT ? as col1, ? as col2", [1, 2])->fetch();

        self::assertEquals("1", $row['col1']);
        self::assertEquals("2", $row['col2']);
    }

    /**
     * @throws Exception
     */
    public function testMultipleNamedParams()
    {
        $db = $this->getDbInstance();

        $row = $db->query("SELECT :var1 as col1, :var2 as col2, :var1 as col3",
            [
                'var1' => 1,
                'var2' => 2,
            ]
        )->fetch();

        self::assertEquals("1", $row['col1']);
        self::assertEquals("2", $row['col2']);
        self::assertEquals("1", $row['col3']);
    }

    /**
     * @throws Exception
     */
    public function testInsert()
    {
        $this->createCleanDatabase();

        $db = $this->getDbInstance();
        $lastInsertId = $db->insert("tabletest", ['col1' => 'testcol1', 'col2' => 'testcol2']);
        self::assertEquals(1, $lastInsertId, "PDOPowered::insert does not returns the last insert id");
        $row = $db->query("SELECT * FROM tabletest WHERE id = ?", [$lastInsertId])->fetch();
        self::assertEquals("testcol1", $row['col1'], "insert does not insert right things in the right place");
        self::assertEquals("testcol2", $row['col2'], "insert does not insert right things in the right place");
    }

    /**
     * @throws Exception
     */
    private function createCleanDatabase()
    {
        $db = $this->getDbInstance();
        $db->query("CREATE DATABASE IF NOT EXISTS {$GLOBALS['DB_DBNAME']} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $db->query("USE {$GLOBALS['DB_DBNAME']}");
        $db->query("create table if not exists tabletest
    (
	id int AUTO_INCREMENT primary key,
	col1 varchar(64) not null,
	col2 varchar(255) not null
	)
		");
        $db->query("TRUNCATE TABLE tabletest");
    }

    /**
     * @throws Exception
     */
    public function testExpressionInInsert()
    {
        $this->createCleanDatabase();

        $db = $this->getDbInstance();
        $today = $db->query("SELECT DATE(NOW()) as today")->fetch()['today'];
        $lastInsertId = $db->insert("tabletest", ['col1' => new Expression("DATE(NOW())"), 'col2' => 'testcol2']);
        $row = $db->query("SELECT * FROM tabletest WHERE id = ?", [$lastInsertId])->fetch();
        self::assertEquals($today, $row['col1'], "insert does not insert right things in the right place");
    }

    /**
     * @throws Exception
     * @throws \Exception
     */
    public function testRollbackTransaction()
    {
        $db = $this->importDbAndFetchInstance();

        $db->beginTransaction();
        $lastInsertId = $db->insert("tabletest", ['col1' => 'testcol1', 'col2' => 'testcol2']);
        $row = $db->query("SELECT * FROM tabletest WHERE id = ?", [$lastInsertId])->fetch();
        self::assertNotEmpty($row);
        $db->rollbackTransaction();

        $row = $db->query("SELECT * FROM tabletest WHERE id = ?", [$lastInsertId])->fetch();
        self::assertEmpty($row);
    }

    /**
     * @throws Exception
     */
    private function importDbAndFetchInstance(): PDOPowered
    {
        $this->createCleanDatabase();
        return $this->getDbInstance();
    }

    /**
     * @throws Exception
     * @throws \Exception
     * @throws \Exception
     */
    public function testCommitTransaction()
    {
        $db = $this->importDbAndFetchInstance();

        $db->beginTransaction();
        $lastInsertId = $db->insert("tabletest", ['col1' => 'testcol1', 'col2' => 'testcol2']);
        $row = $db->query("SELECT * FROM tabletest WHERE id = ?", [$lastInsertId])->fetch();
        self::assertNotEmpty($row);
        $db->commitTransaction();

        $row = $db->query("SELECT * FROM tabletest WHERE id = ?", [$lastInsertId])->fetch();
        self::assertNotEmpty($row);
    }

    /**
     * @throws Exception
     */
    public function testUpdate()
    {
        $db = $this->importDbAndFetchInstance();
        $idRow1 = $db->insert("tabletest", ['col1' => 'testcol1_1', 'col2' => 'testcol2_1']);
        $idRow2 = $db->insert("tabletest", ['col1' => 'testcol1_2', 'col2' => 'testcol2_2']);
        $db->update("tabletest", [
            'col1' => 'updatedCol1',
            'col2' => "updatedCol2"
        ], ['id' => $idRow2]);

        $row1 = $db->query("SELECT* FROM tabletest WHERE id = ?", [$idRow1])->fetch();
        $row2 = $db->query("SELECT* FROM tabletest WHERE id = ?", [$idRow2])->fetch();

        self::assertEquals($row1['col1'], 'testcol1_1');
        self::assertEquals($row1['col2'], 'testcol2_1');
        self::assertEquals($row2['col1'], 'updatedCol1');
        self::assertEquals($row2['col2'], 'updatedCol2');

    }

    /**
     * @throws Exception
     */
    public function testExpressionInUpdate()
    {
        $this->createCleanDatabase();

        $db = $this->getDbInstance();
        $today = $db->query("SELECT DATE(NOW()) as today")->fetch()['today'];
        $lastInsertId = $db->insert("tabletest", ['col1' => "testcol1", 'col2' => 'testcol2']);
        $db->update("tabletest", ['col1' => new Expression("DATE(NOW())")], ['id' => $lastInsertId]);
        $row = $db->query("SELECT * FROM tabletest WHERE id = ?", [$lastInsertId])->fetch();
        self::assertEquals($today, $row['col1'], "insert does not insert right things in the right place");
    }

    /**
     * @throws Exception
     */
    public function testInsertOnDuplicateKeyUpdate()
    {
        $db = $this->importDbAndFetchInstance();

        $idRow1 = $db->insert("tabletest", ['col1' => 'testcol1_1', 'col2' => 'testcol2_1']);
        $db->insertOnDuplicateKeyUpdate("tabletest", ['id' => $idRow1, 'col1' => 'updatedCol1', 'col2' => 'updatedCol2']);

        $row1 = $db->query("SELECT* FROM tabletest WHERE id = ?", [$idRow1])->fetch();

        self::assertEquals('updatedCol1', $row1['col1']);
        self::assertEquals('updatedCol2', $row1['col2']);
    }

    /**
     * @throws Exception
     */
    public function testExpressionInInsertOnDuplicateKeyUpdate()
    {
        $db = $this->importDbAndFetchInstance();

        $today = $db->query("SELECT DATE(NOW()) as today")->fetch()['today'];
        $idRow1 = $db->insert("tabletest", ['col1' => 'testcol1_1', 'col2' => 'testcol2_1']);
        $db->insertOnDuplicateKeyUpdate("tabletest", ['id' => $idRow1, 'col1' => new Expression("DATE(NOW())"), 'col2' => 'updatedCol2']);

        $row1 = $db->query("SELECT* FROM tabletest WHERE id = ?", [$idRow1])->fetch();

        self::assertEquals($row1['col1'], $today);
        self::assertEquals('updatedCol2', $row1['col2']);
    }

    /**
     * @throws Exception
     */
    public function testDelete()
    {
        $db = $this->importDbAndFetchInstance();

        $db->insert("tabletest", ['col1' => 'testcol1_1', 'col2' => 'testcol2_1']);
        $db->insert("tabletest", ['col1' => 'testcol1_2', 'col2' => 'testcol2_2']);
        $db->insert("tabletest", ['col1' => 'testcol1_3', 'col2' => 'testcol2_3']);

        $db->delete("tabletest", ['id' => 2]);

        $rows = $db->query("SELECT * FROM tabletest")->fetchAll();

        self::assertCount(2, $rows);
        self::assertEquals("1", $rows[0]['id']);
        self::assertEquals("3", $rows[1]['id']);

    }

    /**
     * @throws Exception
     */
    public function testExpressionInDelete()
    {
        $db = $this->importDbAndFetchInstance();

        $db->insert("tabletest", ['col1' => 'testcol1_1', 'col2' => 'testcol2_1']);
        $db->insert("tabletest", ['col1' => 'testcol1_2', 'col2' => 'testcol2_2']);
        $db->insert("tabletest", ['col1' => 'testcol1_3', 'col2' => 'testcol2_3']);

        $db->delete("tabletest", ['id' => new Expression("(SELECT 2)")]);

        $rows = $db->query("SELECT * FROM tabletest")->fetchALl();

        self::assertCount(2, $rows);
        self::assertEquals("1", $rows[0]['id']);
        self::assertEquals("3", $rows[1]['id']);

    }

    /**
     * @throws Exception
     */
    public function testRowCount()
    {
        $db = $this->importDbAndFetchInstance();

        $db->insert("tabletest", ['col1' => 'testcol1_1', 'col2' => 'testcol2_1']);
        $db->insert("tabletest", ['col1' => 'testcol1_2', 'col2' => 'testcol2_2']);
        $db->insert("tabletest", ['col1' => 'testcol1_3', 'col2' => 'testcol2_3']);

        $rowCount = $db->query("UPDATE tabletest SET col1 = 'updated' WHERE id > 1")->rowCount();

        self::assertEquals(2, $rowCount);
    }

    /**
     * @throws Exception
     */
    public function testFetchObject()
    {
        $db = $this->importDbAndFetchInstance();

        $db->insert("tabletest", ['col1' => 'testcol1_1', 'col2' => 'testcol2_1']);
        $db->insert("tabletest", ['col1' => 'testcol1_2', 'col2' => 'testcol2_2']);

        $stmt = $db->query("SELECT * FROM tabletest");

        $obj = $stmt->fetchObject();

        self::assertInstanceOf(\stdClass::class, $obj);
        self::assertEquals("testcol1_1", $obj->col1);

        $obj = $stmt->fetchObject();

        self::assertInstanceOf(\stdClass::class, $obj);
        self::assertEquals("testcol1_2", $obj->col1);

        $obj = $stmt->fetchObject();
        self::assertFalse($obj);

    }

    /**
     * @throws Exception
     */
    public function testFetchObjects()
    {
        $db = $this->importDbAndFetchInstance();

        $db->insert("tabletest", ['col1' => 'testcol1_1', 'col2' => 'testcol2_1']);
        $db->insert("tabletest", ['col1' => 'testcol1_2', 'col2' => 'testcol2_2']);

        $stmt = $db->query("SELECT * FROM tabletest");

        $objs = $stmt->fetchObjects();

        self::assertTrue(is_array($objs));
        self::assertCount(2, $objs);

        foreach ($objs as $obj) {
            self::assertInstanceOf(\stdClass::class, $obj);
            self::assertObjectHasAttribute("col1", $obj);
            self::assertObjectHasAttribute("col2", $obj);
        }

    }

    /**
     * @throws Exception
     */
    public function testFetchColumn()
    {
        $db = $this->importDbAndFetchInstance();

        $db->insert("tabletest", ['col1' => 'testcol1_1', 'col2' => 'testcol2_1']);
        $db->insert("tabletest", ['col1' => 'testcol1_2', 'col2' => 'testcol2_2']);

        $stmt = $db->query("SELECT col1, col2 FROM tabletest");

        self::assertEquals("testcol1_1", $stmt->fetchColumn());
        self::assertEquals("testcol1_2", $stmt->fetchColumn());
        self::assertFalse($stmt->fetchColumn());

        $stmt = $db->query("SELECT col1, col2 FROM tabletest");

        self::assertEquals("testcol2_1", $stmt->fetchColumn(1));
        self::assertEquals("testcol2_2", $stmt->fetchColumn(1));
        self::assertFalse($stmt->fetchColumn(1));
    }

    /**
     * @throws Exception
     */
    public function testErrorCode()
    {
        $db = $this->importDbAndFetchInstance();

        try {
            $db->query("SELEKT WRONG SYNTAX");
            self::assertFalse(true, "An Exception should be thrown on a wrong query");
        } catch (\Exception $e) {
            self::assertInstanceOf(\PDOException::class, $e);
            self::assertStringContainsString("42000", $e->getMessage());
        }
    }

    /**
     * @throws Exception
     */
    public function testDebug()
    {
        $db = $this->importDbAndFetchInstance();

        $debug = $db->query("SELECT * FROM tabletest WHERE id IN (:var1,:var2)", ['var1' => 1, 'var2' => 2])->debugDumpParams();

        self::assertTrue(is_string($debug));
    }

    /**
     * @throws Exception
     */
    public function testOverrideAttribute()
    {
        $db = $this->importDbAndFetchInstance();

        $db->onConnect(function (PDOPowered $db) {
            $db->setPDOAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_BOTH);
        });

        $db->insert("tabletest", ['col1' => 'col', 'col2' => 'col2']);

        $row = $db->query("SELECT id,col1, col2 FROM tabletest")->fetch();

        self::assertEquals(6, count($row));
    }

    /**
     * @throws Exception
     */
    public function testFailUpdate()
    {
        $this->expectException(\PDOException::class);
        $db = $this->importDbAndFetchInstance();

        $db->update("unknowntable", ['col' => 1], ['id' => 1]);
    }

    /**
     * @throws Exception
     */
    public function testFailDelete()
    {
        $this->expectException(\PDOException::class);
        $db = $this->importDbAndFetchInstance();

        $db->delete("unknowntable", ['id' => 1]);
    }

    /**
     * @throws Exception
     */
    public function testFailInsert()
    {
        $this->expectException(\PDOException::class);
        $db = $this->importDbAndFetchInstance();

        $db->delete("unknowntable", ['id' => 1]);
    }

    /**
     * @throws Exception
     */
    public function testDebugCallback()
    {
        $db = $this->importDbAndFetchInstance();


        $debugCalled = false;
        $db->onDebug(function () use (&$debugCalled) {
            $debugCalled = true;
        });
        $db->query("SELECT * FROM tabletest WHERE id = :id", ['id' => 199]);
        self::assertTrue($debugCalled);
    }

    /**
     * @throws Exception
     */
    public function testOnConnectionBeforeConnection()
    {
        $db = $this->getDbInstance();
        $onConnectCalled = false;
        $db->onConnect(function () use (&$onConnectCalled) {
            $onConnectCalled = true;
        });
        self::assertFalse($onConnectCalled);
        $db->query("SELECT 1");
        self::assertTrue($onConnectCalled);
    }

    /**
     * @throws Exception
     */
    public function testOnConnectionAfterConnectionIsImmediate()
    {
        $db = $this->getDbInstance();
        $db->query("SELECT 1");
        $onConnectCalled = false;
        $db->onConnect(function () use (&$onConnectCalled) {
            $onConnectCalled = true;
        });
        self::assertTrue($onConnectCalled);
    }

    /**
     * @throws Exception
     */
    public function testSetAttributeBeforeConnection()
    {
        $db = $this->getDbInstance();
        $db->setPDOAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_CLASS);
        $stm = $class = $db->query("SELECT 1 as col");
        $stm->getPDOStatement()->setFetchMode(\PDO::FETCH_CLASS, \stdClass::class);
        $class = $stm->fetch();
        self::assertInstanceOf(\stdClass::class, $class);
    }

    /**
     * @throws Exception
     */
    public function testConnectionWithWrongCredentials()
    {
        $config = new Config(
            "mysql",
            "wrongusername",
            "wrongpass",
            $GLOBALS['DB_HOST'],
            $GLOBALS['DB_PORT'],
            "",
            "utf8"
        );
        $db = new PDOPowered($config);
        $callbackCalled = 0;

        $db->onConnectionFailure(function ($try, \Exception $e) use (&$callbackCalled) {
            $callbackCalled++;
            self::assertEquals(1045, $e->getCode(), "Exception code should be equals to the mysql state error code");
            self::assertEquals($try, $callbackCalled);
        });

        self::assertTrue(true, "lazy loading");

        try {
            $db->query("SELECT * FROM tabletest");
            self::assertTrue(false, "An exception should be thrown if there are connection problem");
        } catch (\PDOException $e) {
            self::assertNotContains("wrongpass", [$e->getMessage()], "Connection error should not contains the password");
            self::assertNotEquals(0, $callbackCalled);
            self::assertEquals(PDOPowered::$MAX_TRY_CONNECTION - 1, $callbackCalled);
        }
    }

    /**
     * @throws Exception
     */
    public function testLazyConnection()
    {
        $db = $this->getInstance();
        self::assertFalse($db->isConnected(), "PDOPowered should support lazy connection by default");
        $stmt = $db->query("SELECT 1");
        self::assertTrue($db->isConnected(), "PDOPowered should be connected after the first query");
        self::assertInstanceOf(\PDOStatement::class, $stmt->getPDOStatement());
    }

    /**
     * @throws Exception
     */
    public function testRemoveListeners()
    {
        $db = $this->getInstance();
        $idConnect = $db->onConnect(function () {
            self::assertTrue(false, "onConnect callback should not be called");
        });
        $idDebug = $db->onDebug(function () {
            self::assertTrue(false, "onDebug callback should not be called");
        });

        $db->removeDebugListener($idDebug);
        $db->removeOnConnectListener($idConnect);


        $db->query("SELECT 1")->fetch();
        self::assertTrue(true);
    }

    public function testRemoveConnectionFailure()
    {
        $config = new Config(
            "mysql",
            "wrongusername",
            "wrongpass",
            $GLOBALS['DB_HOST'],
            $GLOBALS['DB_PORT'],
            "",
            "utf8"
        );
        $db = new PDOPowered($config);
        $idConnectionFailure = $db->onConnectionFailure(function () {
            self::assertTrue(false, "onConnectionFailure callback should not be called");
        });
        $db->removeConnectionFailureListener($idConnectionFailure);
        try {
            $db->query("SELECT 1")->fetch();
            self::assertFalse(true, "this code should be unreachable");
        } catch (\Exception $e) {
            self::assertTrue(true);
        }
    }

    /**
     * @throws Exception
     */
    public function testResultSetTraversable()
    {
        $db = $this->importDbAndFetchInstance();
        $db->insert("tabletest", ['col1' => 1, 'col2' => 2]);
        $db->insert("tabletest", ['col1' => 1, 'col2' => 2]);

        $counterIndex = 0;

        foreach ($db->query("SELECT * FROM tabletest") as $index => $row) {
            self::assertEquals($counterIndex, $index);
            self::assertArrayHasKey('col1', $row);
            self::assertArrayHasKey('col2', $row);
            $counterIndex++;
        }

    }

    /**
     * @throws Exception
     */
    public function testResultSetCloseCursor()
    {
        $db = $this->importDbAndFetchInstance();
        $db->insert("tabletest", ['col1' => 1, 'col2' => 2]);
        $db->insert("tabletest", ['col1' => 1, 'col2' => 2]);

        $counterIndex = 0;
        $stmt = $db->query("SELECT * FROM tabletest");
        foreach ($stmt as $index => $row) {
            self::assertEquals($counterIndex, $index);
            self::assertArrayHasKey('col1', $row);
            self::assertArrayHasKey('col2', $row);
            $stmt->closeCursor();
            $counterIndex++;
        }
        self::assertEquals(1, $counterIndex);
    }

    public function testOnMethodsWantCallback() {
        $this->expectException(Exception::class);
        $db = $this->importDbAndFetchInstance();

        $db->onDebug("noCallbackHere");
    }

    /**
     * @throws Exception
     */
    public function testFastQueryParam() {
        $db = $this->importDbAndFetchInstance();

        $db->insert("tabletest", [
            'col1' => 1,
            'col2' => 2,
        ]);

        $db->update("tabletest", [
            'col1' => new ParamJSON([1,2,3])
        ], ['col1' => 1]);

        $row = $db->query("SELECT * FROM tabletest WHERE col1 = ?", [new ParamJSON([1,2,3])])->fetch();
        self::assertEquals(json_encode([1,2,3]), $row['col1']);

        $db->delete("tabletest", [
            'col1' => new ParamJSON([1,2,3])
        ]);

        $row = $db->query("SELECT * FROM tabletest WHERE col1 = ?", [new ParamJSON([1,2,3])])->fetch();
        self::assertFalse($row);

        $db->insert("tabletest", [
            'col1' => new ParamJSON([1,2,3]),
            'col2' => 2,
        ]);

        $row = $db->query("SELECT * FROM tabletest WHERE col1 = ?", [new ParamJSON([1,2,3])])->fetch();
        self::assertEquals(json_encode([1,2,3]), $row['col1']);

    }

    /**
     * @throws Exception
     * @throws \Exception
     */
    public function testQueryOnDebugMode()
    {
        $db = $this->importDbAndFetchInstance();

        $db->onDebug(DebugParser::onParse(function ($info) {
            self::assertNotEmpty($info);
        }));

        $db->query("SELECT * FROM tabletest")->fetchAll();
        $db->beginTransaction();
        $db->insert("tabletest", ['col1' => 1, 'col2' => new ParamInt(3)]);
        $db->update("tabletest", ['col1' => 1, 'col2' => new ParamInt(1)], ['id' => new ParamInt(1)]);
        $db->commitTransaction();
        $db->query("SELECT * FROM tabletest WHERE id = :id", ['id' => 1])->fetchAll();
        $db->query("SELECT * FROM tabletest WHERE id = ?", [1])->fetchAll();
    }
}