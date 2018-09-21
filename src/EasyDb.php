<?php

namespace rain1\EasyDb;

class EasyDb
{

    const CONNECTION_ERROR = 1;

    private $pdo;
    private $_isConnected = false;

    private $connectionTry = 0;
    public static $MAX_TRY_CONNECTION = 3;

    /**
     * @var DbConfig
     */
    private $dbConfig;

    private $callbacks = [
        'connectFailure' => [],
        'connect' => [],
        'debug' => []
    ];

    public function __construct(DbConfig $dbConfig)
    {
        $this->dbConfig = $dbConfig;
    }

    public function onConnectionFailure($callback)
    {
        $this->_addListener('connectFailure', $callback);
    }

    public function onDebug($callback)
    {
        $this->_addListener('debug', $callback);
    }

    public function onConnect($callback)
    {
        if ($this->_isConnected)
            $callback($this);
        else
            $this->_addListener('connect', $callback);
    }

    private function _addListener($eventName, $callback)
    {
        $this->callbacks[$eventName][] = $callback;
    }

    public function query($query, $params = []): ResultSet
    {

        $stmt = $this->getPDO()->prepare($query);

        if ($stmt === false)
            throw new Exception("Cannot prepare query " . json_encode($query));


        $res = $stmt->execute($params);

        $this->debug("query", $stmt, $params);

        if (!$res)
            throw new Exception("Query Error ({$stmt->errorCode()}: " . json_encode($stmt->errorInfo()));

        return new ResultSet($stmt);
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

        $params = array_filter($params, function ($el) {
            return !($el instanceof Expression);
        });

        $this->debug("insert" . ($withOnDuplicateKey ? "onDuplicateKey" : ""), $sth);

        foreach ($params as $field => $value)
            $sth->bindValue(":$field", $value, is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR);

        $res = $sth->execute();

        if (!$res)
            throw new Exception("Insert Error  ({$sth->errorCode()}) " . json_encode($sth->errorInfo()));

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
            else
                $parts[] = "$field = {$value->get()}";

        $conditionPart = implode(" AND ", $parts);

        $sth = $db->prepare("DELETE FROM $table WHERE $conditionPart");

        $this->debug("delete", $sth);

        foreach ($where as $field => $value)
            if (!($value instanceof Expression))
                $sth->bindValue(":$field", $value);

        $res = $sth->execute();

        if (!$res)
            throw new Exception("Delete failed ({$sth->errorCode()}) " . json_encode($sth->errorInfo()));

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

        $this->debug("update", $sth);

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
            throw new Exception("Update failed ({$sth->errorCode()})  " . json_encode($sth->errorInfo(), true));

        return $sth->rowCount();

    }


    public function beginTransaction()
    {
        $db = $this->getPDO();
        $this->debug("beginTransaction");
        $db->beginTransaction();
    }

    public function rollbackTransaction()
    {
        $db = $this->getPDO();
        $this->debug("rollbackTransaction");
        $db->rollBack();
    }

    public function commitTransaction()
    {
        $db = $this->getPDO();
        $this->debug("commitTransaction");
        $db->commit();
    }

    public function isConnected()
    {
        return $this->_isConnected;
    }

    private function getPDO()
    {
        return $this->pdo ?: $this->connectAndFetchPDOInstance();
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

    public function setPDOAttribute($attributeName, $attributeValue)
    {
        if ($this->pdo instanceof \PDO)
            $this->pdo->setAttribute($attributeName, $attributeValue);
        else
            $this->onConnect(function () use ($attributeName, $attributeValue) {
                $this->setPDOAttribute($attributeName, $attributeValue);
            });
    }

    private function connectAndFetchPDOInstance()
    {
        try {
            $this->pdo = new \PDO($this->dbConfig->getConnectionString(), $this->dbConfig->getUser(), $this->dbConfig->getPassword());
            unset($this->dbConfig);
            $this->_isConnected = true;
            $this->pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
            $this->trigger("connect", $this);
            return $this->pdo;
        } catch (\Exception $e) {

            if (++$this->connectionTry < self::$MAX_TRY_CONNECTION) {
                $this->trigger("connectFailure", $this->connectionTry);
                return $this->connectAndFetchPDOInstance();
            }

            $hasPassword = !!$this->dbConfig->getPassword();
            $this->hideSensibileInfos();
            throw new Exception("Failed to connect Mysql server. Using password: " . ($hasPassword ? "YES" : "NO"), self::CONNECTION_ERROR);
        }
    }

    private function trigger($eventName, ...$args)
    {
        foreach ($this->callbacks[$eventName] as $callback)
            call_user_func_array($callback, $args);

    }

    private function debug()
    {
        call_user_func_array([$this, "trigger"], array_merge(["debug"], func_get_args()));
    }

    private function hideSensibileInfos()
    {
        unset($this->dbConfig);
    }
}


class ResultSet
{
    private $statement;

    public function __construct(\PDOStatement $PDOStatement)
    {
        $this->statement = $PDOStatement;

    }

    public function getPDOStatement()
    {
        return $this->statement;
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

    public function fetchAll($fetch_style = null, $fetch_argument = null, $ctor_args = null)
    {
        return call_user_func_array(
            [$this->statement, "fetchAll"],
            array_filter([$fetch_style, $fetch_argument, $ctor_args])
        );
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

    public function debugDumpParams()
    {
        ob_start();
        $this->statement->debugDumpParams();
        return ob_get_clean();
    }


}