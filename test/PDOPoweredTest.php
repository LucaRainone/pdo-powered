<?php

namespace rain1\PDOPowered\test;

use PHPUnit\Framework\TestCase;
use rain1\PDOPowered\Config\Config;
use rain1\PDOPowered\Exception;
use rain1\PDOPowered\Expression;
use rain1\PDOPowered\Param\ParamJSON;
use rain1\PDOPowered\Param\ParamString;
use rain1\PDOPowered\PDOPowered;

class PDOPoweredTest extends TestCase
{

    private $db;

    public function testPhpUnitXMlDist()
    {
        self::assertArrayHasKey("DB_USER", $GLOBALS, "rename phpunit.dist.xml in phpunit.xml and/or do the right thing");
    }

    public function testNativePrepare()
    {
        $db = $this->getDbInstance();
        $statement = $db->prepare("SELECT * FROM tabletest");
        self::assertInstanceOf(\PDOStatement::class, $statement);
    }

    private function getDbInstance()
    {

        if (!$this->db)
            $this->db = $this->getInstance();

        return $this->db;

    }

    private function getInstance()
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

    public function testSimpleQuery()
    {

        $db = $this->getDbInstance();

        $row = $db->query("SELECT 1 as const")->fetch();

        self::assertTrue($db->isConnected(), "Database should be connected at this point");

        self::assertArrayHasKey("const", $row, "default resultset is ASSOC");
        self::assertArrayNotHasKey("0", $row, "default resultset should me ASSOC");
    }

    /**
     * @expectedException \rain1\PDOPowered\Exception
     */
    public function testSyntaxErrorQueryThrowsException()
    {
        $db = $this->getDbInstance();
        $db->query("SELEKT 1 as const");

    }

    public function testQuestionMarkParam()
    {
        $db = $this->getDbInstance();

        $row = $db->query("SELECT ? as col", [1])->fetch();

        self::assertEquals("1", $row['col']);
    }

    public function testCustomParam()
    {
        $db = $this->getDbInstance();

        $col = $db->query("SELECT ? as col", [new ParamString("Hello")])->fetchColumn();
        self::assertEquals("Hello", $col);

        $col = $db->query("SELECT :myParam as col", ['myParam' => new ParamString("Hello")])->fetchColumn();
        self::assertEquals("Hello", $col);
    }

    public function testNamedParam()
    {
        $db = $this->getDbInstance();

        $row = $db->query("SELECT :var as col", ['var' => "myString"])->fetch();

        self::assertEquals("myString", $row['col']);
    }

    public function testMultipleQuestionMarkParam()
    {
        $db = $this->getDbInstance();

        $row = $db->query("SELECT ? as col1, ? as col2", [1, 2])->fetch();

        self::assertEquals("1", $row['col1']);
        self::assertEquals("2", $row['col2']);
    }

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

    private function createCleanDatabase()
    {
        $db = $this->getDbInstance();
        $db->query("CREATE DATABASE IF NOT EXISTS {$GLOBALS['DB_DBNAME']} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $db->query("USE {$GLOBALS['DB_DBNAME']}");
        $db->query("create table if not exists tabletest
(
	id int auto_increment
		primary key,
	col1 varchar(64) not null,
	col2 varchar(255) not null
	)
		");
        $db->query("TRUNCATE TABLE tabletest");
    }

    public function testExpressionInInsert()
    {
        $this->createCleanDatabase();

        $db = $this->getDbInstance();
        $today = $db->query("SELECT DATE(NOW()) as today")->fetch()['today'];
        $lastInsertId = $db->insert("tabletest", ['col1' => new Expression("DATE(NOW())"), 'col2' => 'testcol2']);
        $row = $db->query("SELECT * FROM tabletest WHERE id = ?", [$lastInsertId])->fetch();
        self::assertEquals($today, $row['col1'], "insert does not insert right things in the right place");
    }

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

    private function importDbAndFetchInstance()
    {
        $this->createCleanDatabase();
        return $this->getDbInstance();
    }

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

    public function testInsertOnDuplicateKeyUpdate()
    {
        $db = $this->importDbAndFetchInstance();

        $idRow1 = $db->insert("tabletest", ['col1' => 'testcol1_1', 'col2' => 'testcol2_1']);
        $db->insertOnDuplicateKeyUpdate("tabletest", ['id' => $idRow1, 'col1' => 'updatedCol1', 'col2' => 'updatedCol2']);

        $row1 = $db->query("SELECT* FROM tabletest WHERE id = ?", [$idRow1])->fetch();

        self::assertEquals($row1['col1'], 'updatedCol1');
        self::assertEquals($row1['col2'], 'updatedCol2');
    }

    public function testExpressionInInsertOnDuplicateKeyUpdate()
    {
        $db = $this->importDbAndFetchInstance();

        $today = $db->query("SELECT DATE(NOW()) as today")->fetch()['today'];
        $idRow1 = $db->insert("tabletest", ['col1' => 'testcol1_1', 'col2' => 'testcol2_1']);
        $db->insertOnDuplicateKeyUpdate("tabletest", ['id' => $idRow1, 'col1' => new Expression("DATE(NOW())"), 'col2' => 'updatedCol2']);

        $row1 = $db->query("SELECT* FROM tabletest WHERE id = ?", [$idRow1])->fetch();

        self::assertEquals($row1['col1'], $today);
        self::assertEquals($row1['col2'], 'updatedCol2');
    }

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

    public function testRowCount()
    {
        $db = $this->importDbAndFetchInstance();

        $db->insert("tabletest", ['col1' => 'testcol1_1', 'col2' => 'testcol2_1']);
        $db->insert("tabletest", ['col1' => 'testcol1_2', 'col2' => 'testcol2_2']);
        $db->insert("tabletest", ['col1' => 'testcol1_3', 'col2' => 'testcol2_3']);

        $rowCount = $db->query("UPDATE tabletest SET col1 = 'updated' WHERE id > 1")->rowCount();

        self::assertEquals(2, $rowCount);
    }

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

    public function testErrorCode()
    {
        $db = $this->importDbAndFetchInstance();

        try {
            $db->query("SELEKT WRONG SYNTAX");
            self::assertFalse(true, "An Exception should be thrown on a wrong query");
        } catch (\Exception $e) {
            self::assertInstanceOf(Exception::class, $e);
            self::assertContains("42000", $e->getMessage());
        }
    }

    public function testDebug()
    {
        $db = $this->importDbAndFetchInstance();

        $debug = $db->query("SELECT * FROM tabletest WHERE id IN (:var1,:var2)", ['var1' => 1, 'var2' => 2])->debugDumpParams();

        self::assertTrue(is_string($debug));
    }

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
     * @expectedException Exception
     */
    public function testFailUpdate()
    {
        $db = $this->importDbAndFetchInstance();

        $db->update("unknowntable", ['col' => 1], ['id' => 1]);
    }

    /**
     * @expectedException Exception
     */
    public function testFailDelete()
    {
        $db = $this->importDbAndFetchInstance();

        $db->delete("unknowntable", ['id' => 1]);
    }

    /**
     * @expectedException Exception
     */
    public function testFailInsert()
    {
        $db = $this->importDbAndFetchInstance();

        $db->delete("unknowntable", ['id' => 1]);
    }

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

    public function testSetAttributeBeforeConnection()
    {
        $db = $this->getDbInstance();
        $db->setPDOAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_CLASS);
        $stm = $class = $db->query("SELECT 1 as col");
        $stm->getPDOStatement()->setFetchMode(\PDO::FETCH_CLASS, \stdClass::class);
        $class = $stm->fetch();
        self::assertInstanceOf(\stdClass::class, $class);
    }

    public function testMysqlNotAvailable()
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
            $db->query("SELECT * FROM user");
            self::assertTrue(false, "An exception should be thrown if there are connection problem");
        } catch (Exception $e) {
            self::assertNotContains("wrongpass", $e->getMessage(), "Connection error should not contains the password");
            self::assertNotEquals(0, $callbackCalled);
            self::assertEquals(PDOPowered::$MAX_TRY_CONNECTION - 1, $callbackCalled);
        }
    }

    public function testLazyConnection()
    {
        $db = $this->getInstance();
        self::assertFalse($db->isConnected(), "PDOPowered should support lazy connection by default");
        $stmt = $db->query("SELECT 1");
        self::assertTrue($db->isConnected(), "PDOPowered should be connected after the first query");
        self::assertInstanceOf(\PDOStatement::class, $stmt->getPDOStatement());
    }

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

    /**
     * @expectedException Exception
     */
    public function testOnMethodsWantCallback() {
        $db = $this->importDbAndFetchInstance();

        $db->onDebug("noCallbackHere");
    }

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
}