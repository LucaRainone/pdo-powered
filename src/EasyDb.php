<?php

namespace rain1\EasyDb;

class EasyDb
{

    const CONNECTION_ERROR = 1;

    private $pdo;

    private static $connectionTry = 0;
    public static $MAX_TRY_CONNECTION = 3;

    /**
     * @var DbConfig
     */
    private $dbConfig;

    public function __construct(DbConfig $dbConfig)
    {
        $this->dbConfig = $dbConfig;
    }

    public function query($query, $params = [])
    {

        $stmt = $this->getPDO()->prepare($query);

        if ($stmt === false)
            throw new Exception("Cannot prepare query " . json_encode($query));

        $res = $stmt->execute($params);

        if (!$res)
            throw new Exception("Query Error " . json_encode($stmt->errorInfo()));

        return new ResultSet($stmt);
    }

    private function _buildFieldsAndValues($params)
    {
        $fields = [];
        $values = [];
        foreach ($params as $field => $value) {
            if ($value instanceof Expression) {
                $values[] = $value->get();
            } else {
                $values[] = ":$field";
            }
            $fields[] = $field;
        }
        return [$fields, $values];
    }

    private function _insertOrInsertOnDuplicateKey($table, array $params, $withOnDuplicateKey = false)
    {
        $db = $this->getPDO();

        list($fields, $values) = $this->_buildFieldsAndValues($params);

        $implodedFields = implode(",", $fields);
        $implodedValues = implode(",", $values);

        $tail = "";

        if ($withOnDuplicateKey) {
            $updateFields = array_map(function ($field) {
                return "$field = VALUES($field)";
            }, $fields);
            $tail = " ON DUPLICATE KEY UPDATE " . implode(", ", $updateFields);
        }


        $qry = "INSERT INTO $table ($implodedFields) VALUES($implodedValues)$tail";

        $sth = $db->prepare($qry);

        foreach ($params as $field => $value)
            if (!($value instanceof Expression))
                $sth->bindValue(":$field", $value, is_int($value)? \PDO::PARAM_INT : \PDO::PARAM_STR);


        $res = $sth->execute();
        if (!$res)
            throw new Exception("Insert Error " . json_encode($sth->errorInfo()));

        return $db->lastInsertId();
    }

    public function insert($table, array $params)
    {
        return $this->_insertOrInsertOnDuplicateKey($table, $params);
    }

    public function insertOnDuplicateKeyUpdate($table, array $params)
    {
        return $this->_insertOrInsertOnDuplicateKey($table, $params, true);
    }

    public function delete($table, $where)
    {

        $db = $this->getPDO();

        $parts = [];
        foreach ($where as $field => $value)
            if (!($value instanceof Expression))
                $parts[] = "$field = :$field";

        $conditionPart = implode(" AND ", $parts);

        $sth = $db->prepare("DELETE FROM $table WHERE $conditionPart");

        foreach ($where as $field => $value) {
            $sth->bindValue(":$field", $value);
        }
        $res = $sth->execute();

        if (!$res)
            throw new Exception("Update failed " . json_encode($sth->errorInfo()));

        return $sth->rowCount();
    }

    public function update($table, array $array, array $where)
    {

        $db = $this->getPDO();

        $parts = [];
        foreach ($array as $field => $value)
            $parts[] = ($value instanceof Expression) ? "$field = {$value->get()}" : "$field = :$field";


        $setPart = implode(", ", $parts);


        $parts = [];
        foreach ($where as $field => $value)
            if (!($value instanceof Expression))
                $parts[] = "$field = :$field";

        $conditionPart = implode(" AND ", $parts);

        $sth = $db->prepare("UPDATE $table SET $setPart WHERE $conditionPart");
        foreach ($array as $field => $value) {
            if (!($value instanceof Expression)) {

                if (is_int($value))
                    $sth->bindValue(":$field", $value, \PDO::PARAM_INT);
                else
                    $sth->bindValue(":$field", $value, \PDO::PARAM_STR);

            }
        }
        foreach ($where as $field => $value) {
            $sth->bindValue(":$field", $value);
        }
        $res = $sth->execute();

        if (!$res)
            throw new Exception("Update failed " . print_r($sth->errorInfo(), true));

        return $sth->rowCount();

    }


    public function beginTransaction()
    {
        $db = $this->getPDO();
        $db->beginTransaction();
    }

    public function rollbackTransaction()
    {
        $db = $this->getPDO();
        $db->rollBack();
    }

    public function commitTransaction()
    {
        $db = $this->getPDO();
        $db->commit();
    }

    private function getPDO()
    {
        return $this->pdo ?: $this->connectAndFetchPDOInstance();
    }

    private function connectAndFetchPDOInstance()
    {
        try {
            $this->pdo = new \PDO($this->dbConfig->getConnectionString(), $this->dbConfig->getUser(), $this->dbConfig->getPassword());
            unset($this->dbConfig);
            return $this->pdo;
        } catch (\Exception $e) {
            error_log($e->getMessage());

            if(self::$connectionTry++ < self::$MAX_TRY_CONNECTION) {
                sleep(1);
                return $this->connectAndFetchPDOInstance();
            }
            $hasPassword = !!$this->dbConfig->getPassword();
            unset($this->dbConfig);
            throw new Exception("Failed to connect Mysql server. Using password: " . ($hasPassword ? "YES" : "NO"), self::CONNECTION_ERROR);
        }
    }
}


class ResultSet
{
    private $statement;

    public function __construct(\PDOStatement $PDOStatement)
    {
        return $this->statement = $PDOStatement;
    }

    public function fetch($fetch_style = null, $cursor_orientation = \PDO::FETCH_ORI_NEXT, $cursor_offset = 0)
    {
        return $this->statement->fetch($fetch_style, $cursor_orientation, $cursor_offset);
    }

    public function rowCount()
    {
        return $this->statement->rowCount();
    }

    public function fetchColumn($column_number = 0)
    {
        return $this->statement->fetchColumn($column_number);
    }

    public function fetchAll()
    {
        return $this->statement->fetchAll();
    }

    public function fetchObject($class_name = "\\stdClass", array $ctor_args = array())
    {
        return $this->statement->fetchObject($class_name, $ctor_args);
    }

    public function fetchObjects($class_name = "\\stdClass", array $ctor_args = array())
    {
        $rows = [];
        while (($_row = $this->statement->fetchObject($class_name, $ctor_args)))
            $rows[] = $_row;

        return $rows;
    }

    public function errorCode()
    {
        return $this->statement->errorCode();
    }

    public function errorInfo()
    {
        return $this->statement->errorInfo();
    }

    public function setAttribute($attribute, $value)
    {
        return $this->statement->setAttribute($attribute, $value);
    }

    public function getAttribute($attribute)
    {
        return $this->statement->getAttribute($attribute);
    }

    public function columnCount()
    {
        return $this->statement->columnCount();
    }

    public function getColumnMeta($column)
    {
        return $this->statement->getColumnMeta($column);
    }

    public function setFetchMode($mode, $classNameObject, array $ctorarfg)
    {
        return $this->statement->setFetchMode($mode, $classNameObject, $ctorarfg);
    }

    public function nextRowset()
    {
        return $this->statement->nextRowset();
    }

    public function closeCursor()
    {
        return $this->statement->closeCursor();
    }

    public function debugDumpParams()
    {
        return $this->statement->debugDumpParams();
    }


}