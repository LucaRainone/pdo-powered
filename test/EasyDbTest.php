<?php

namespace rain1\EasyDb\test;

use PHPUnit\Framework\TestCase;
use rain1\EasyDb\DbConfig;
use rain1\EasyDb\EasyDb;
use rain1\EasyDb\Exception;
use rain1\EasyDb\Expression;

class EasyDbTest extends TestCase
{

    private $db;

    public function testPhpUnitXMlDist()
    {
        self::assertArrayHasKey("DB_USER", $GLOBALS, "rename phpunit.dist.xml in phpunit.xml and/or do the right thing");
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
     * @expectedException \rain1\EasyDb\Exception
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
        self::assertEquals(1, $lastInsertId, "EasyDb::insert does not returns the last insert id");
        $row = $db->query("SELECT * FROM tabletest WHERE id = ?", [$lastInsertId])->fetch();
        self::assertEquals("testcol1", $row['col1'], "insert does not insert right things in the right place");
        self::assertEquals("testcol2", $row['col2'], "insert does not insert right things in the right place");
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

    public function testOverrideAttribute() {
        $db = $this->importDbAndFetchInstance();

        $db->onConnect(function(EasyDb $db) {
            $db->setPDOAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_BOTH);
        });

        $db->insert("tabletest", ['col1'=> 'col', 'col2' => 'col2']);

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
        $db->onDebug(function ($type, \PDOStatement $stmt = null, ...$args) use(&$debugCalled) {
            echo "TYPE $type: " . $stmt->queryString . " " . json_encode($args);
            $debugCalled = true;
        });
        $db->query("SELECT * FROM tabletest WHERE id = :id", ['id' => 199]);
        self::assertTrue($debugCalled);
    }

    public function testMysqlNotAvailable()
    {
        $config = new DbConfig(
            "",
            "wrongusername",
            "wrongpass",
            $GLOBALS['DB_HOST'],
            $GLOBALS['DB_PORT'],
            "utf8"
        );
        $db = new EasyDb($config);
        $callbackCalled = 0;

        $db->onConnectionFailure(function ($try) use (&$callbackCalled) {
            $callbackCalled++;
            self::assertEquals($try, $callbackCalled);
        });

        self::assertTrue(true, "lazy loading");

        try {
            $db->query("SELECT * FROM user");
            self::assertTrue(false, "An exception should be thrown if there are connection problem");
        } catch (Exception $e) {
            self::assertNotContains("wrongpass", $e->getMessage(), "Connection error should not contains the password");
            self::assertNotEquals(0, $callbackCalled);
            self::assertEquals(EasyDb::$MAX_TRY_CONNECTION - 1, $callbackCalled);
        }
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

    private function getDbInstance()
    {

        if (!$this->db) {
            $config = new DbConfig(
                "",
                $GLOBALS['DB_USER'],
                $GLOBALS['DB_PASSWD'],
                $GLOBALS['DB_HOST'],
                $GLOBALS['DB_PORT'],
                "utf8"
            );
            $this->db = new EasyDb($config);
            self::assertFalse($this->db->isConnected(), "EasyDb should support lazy connection by default");
            $stmt = $this->db->query("SELECT 1");
            self::assertTrue($this->db->isConnected(), "EasyDb should be connected after the first query");
            self::assertInstanceOf(\PDOStatement::class, $stmt->getPDOStatement());
        }

        return $this->db;

    }
    
    private function importDbAndFetchInstance() {
        $this->createCleanDatabase();
        return $this->getDbInstance();
    }
}