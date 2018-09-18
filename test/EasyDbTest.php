<?php

namespace rain1\EasyDb\test;

use PHPUnit\Framework\TestCase;
use rain1\EasyDb\DbConfig;
use rain1\EasyDb\EasyDb;
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

        $row  = $db->query("SELECT 1 as const")->fetch();

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
        $this->createCleanDatabase();
        $db = $this->getDbInstance();

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
        $this->createCleanDatabase();
        $db = $this->getDbInstance();

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
        $this->createCleanDatabase();
        $db = $this->getDbInstance();
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
        $this->createCleanDatabase();
        $db = $this->getDbInstance();

        $idRow1 = $db->insert("tabletest", ['col1' => 'testcol1_1', 'col2' => 'testcol2_1']);
        $db->insertOnDuplicateKeyUpdate("tabletest", ['id' => $idRow1, 'col1' => 'updatedCol1', 'col2' => 'updatedCol2']);

        $row1 = $db->query("SELECT* FROM tabletest WHERE id = ?", [$idRow1])->fetch();

        self::assertEquals($row1['col1'], 'updatedCol1');
        self::assertEquals($row1['col2'], 'updatedCol2');
    }

    public function testExpressionInInsertOnDuplicateKeyUpdate()
    {
        $this->createCleanDatabase();
        $db = $this->getDbInstance();

        $today = $db->query("SELECT DATE(NOW()) as today")->fetch()['today'];
        $idRow1 = $db->insert("tabletest", ['col1' => 'testcol1_1', 'col2' => 'testcol2_1']);
        $db->insertOnDuplicateKeyUpdate("tabletest", ['id' => $idRow1, 'col1' => new Expression("DATE(NOW())"), 'col2' => 'updatedCol2']);

        $row1 = $db->query("SELECT* FROM tabletest WHERE id = ?", [$idRow1])->fetch();

        self::assertEquals($row1['col1'], $today);
        self::assertEquals($row1['col2'], 'updatedCol2');
    }

    public function testDelete()
    {
        $this->createCleanDatabase();
        $db = $this->getDbInstance();

        $db->insert("tabletest", ['col1' => 'testcol1_1', 'col2' => 'testcol2_1']);
        $db->insert("tabletest", ['col1' => 'testcol1_2', 'col2' => 'testcol2_2']);
        $db->insert("tabletest", ['col1' => 'testcol1_3', 'col2' => 'testcol2_3']);

        $db->delete("tabletest", ['id' => 2]);

        $rows = $db->query("SELECT * FROM tabletest")->fetchALl();

        self::assertCount(2, $rows);
        self::assertEquals("1", $rows[0]['id']);
        self::assertEquals("3", $rows[1]['id']);

    }

    public function testExpressionInDelete()
    {
        $this->createCleanDatabase();
        $db = $this->getDbInstance();

        $db->insert("tabletest", ['col1' => 'testcol1_1', 'col2' => 'testcol2_1']);
        $db->insert("tabletest", ['col1' => 'testcol1_2', 'col2' => 'testcol2_2']);
        $db->insert("tabletest", ['col1' => 'testcol1_3', 'col2' => 'testcol2_3']);

        $db->delete("tabletest", ['id' => new Expression("(SELECT 2)")]);

        $rows = $db->query("SELECT * FROM tabletest")->fetchALl();

        self::assertCount(2, $rows);
        self::assertEquals("1", $rows[0]['id']);
        self::assertEquals("3", $rows[1]['id']);

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
            $this->db->query("SELECT 1")->fetch();
            self::assertTrue($this->db->isConnected(), "EasyDb should be connected after the first query");
        }
        return $this->db;

    }
}