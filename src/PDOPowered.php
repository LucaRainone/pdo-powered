<?php

namespace rain1\PDOPowered;

use rain1\PDOPowered\Param\ParamInterface;

class PDOPowered
{

    const CONNECTION_ERROR = 1;
    public static $MAX_TRY_CONNECTION = 3;
    private $pdo;
    private $_isConnected = false;
    private $connectionTry = 0;
    /**
     * @var Config
     */
    private $dbConfig;

    private $callbacks = [
        'connectFailure' => [],
        'connect' => [],
        'debug' => []
    ];

    public function __construct(Config $dbConfig)
    {
        $this->dbConfig = $dbConfig;
    }

    public function onConnectionFailure($callback)
    {
        return $this->_addListener('connectFailure', $callback);
    }

    private function _addListener($eventName, $callback)
    {
        if (!is_callable($callback))
            throw new Exception("expected a callable on on* methods");

        if (!isset($this->callbacks[$eventName]))
            throw new Exception("Unknown $eventName event");

        $this->callbacks[$eventName][] = $callback;
        return count($this->callbacks[$eventName]);
    }

    public function removeConnectionFailureListener($idListener)
    {
        $this->_removeListener('connectFailure', $idListener);
    }

    private function _removeListener($namespace, $idListener)
    {
        $index = $idListener - 1;
        if (isset($this->callbacks[$namespace][$index]))
            unset($this->callbacks[$namespace][$index]);
    }

    public function onDebug($callback)
    {
        return $this->_addListener('debug', $callback);
    }

    public function removeDebugListener($idListener)
    {
        $this->_removeListener('debug', $idListener);
    }

    public function removeOnConnectListener($idListener)
    {
        $this->_removeListener('connect', $idListener);
    }

    public function query($query, $params = []): ResultSet
    {

        $stmt = $this->getPDO()->prepare($query);

        if ($stmt === false)
            throw new Exception("Cannot prepare query " . json_encode($query));

        $questionMark = (count($params) && key($params) === 0);
        foreach ($params as $index => $param)
            $this->_bindValue($stmt, $questionMark ? $index + 1 : $index, $param);

        $res = $stmt->execute();

        $this->debug("query", $stmt, $query, $params);

        if (!$res)
            throw new Exception("Query Error ({$stmt->errorCode()}: " . json_encode($stmt->errorInfo()));

        return new ResultSet($stmt);
    }

    protected function getPDO()
    {
        return $this->pdo ?: $this->connectAndFetchPDOInstance();
    }

    private function connectAndFetchPDOInstance()
    {
        try {
            $this->pdo = new \PDO($this->dbConfig->getConnectionString(), $this->dbConfig->getUser(), $this->dbConfig->getPassword());
        } catch (\Exception $e) {
            if (++$this->connectionTry < self::$MAX_TRY_CONNECTION) {
                $this->trigger("connectFailure", $this->connectionTry, $e);
                return $this->connectAndFetchPDOInstance();
            }

            $hasPassword = !!$this->dbConfig->getPassword();
            $this->hideSensibileInfos();
            throw new Exception("Failed to connect Mysql server. Using password: " . ($hasPassword ? "YES" : "NO"), self::CONNECTION_ERROR);
        }
        unset($this->dbConfig);
        $this->_isConnected = true;
        $this->pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
        $this->trigger("connect", $this);
        return $this->pdo;
    }

    private function trigger($eventName, ...$args)
    {
        foreach ($this->callbacks[$eventName] as $callback)
            call_user_func_array($callback, $args);

    }

    private function hideSensibileInfos()
    {
        unset($this->dbConfig);
    }

    private function _bindValue(\PDOStatement $stmt, $name, $value)
    {

        if ($value instanceof ParamInterface)
            $stmt->bindValue($name, $value->getValue(), ...$value->getArguments());
        else
            $stmt->bindValue($name, $value, is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
    }

    private function debug()
    {
        call_user_func_array([$this, "trigger"], array_merge(["debug"], func_get_args()));
    }

    public function prepare(...$args): \PDOStatement
    {
        $pdo = $this->getPDO();
        return call_user_func_array([$pdo, "prepare"], $args);
    }

    public function insert($table, array $params)
    {
        return $this->_insertOrInsertOnDuplicateKey($table, $params);
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

        $this->debug("insert" . ($withOnDuplicateKey ? "onDuplicateKey" : ""), $sth, $qry, $params);

        foreach ($params as $field => $value)
            $sth->bindValue(":$field", $value, is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR);

        $res = $sth->execute();

        if (!$res)
            throw new Exception("Insert Error  ({$sth->errorCode()}) " . json_encode($sth->errorInfo()));

        return $db->lastInsertId();
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

        foreach ($array as $field => $value)
            if (!($value instanceof Expression))
                $this->_bindValue($sth, $field, $value);


        foreach ($where as $field => $value)
            if (!($value instanceof Expression))
                $this->_bindValue($sth, $field, $value);

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

    public function setPDOAttribute($attributeName, $attributeValue)
    {
        if ($this->pdo instanceof \PDO)
            $this->pdo->setAttribute($attributeName, $attributeValue);
        else
            $this->onConnect(function () use ($attributeName, $attributeValue) {
                $this->setPDOAttribute($attributeName, $attributeValue);
            });
    }

    public function onConnect($callback)
    {
        if ($this->_isConnected)
            $callback($this);
        else
            return $this->_addListener('connect', $callback);

        return null;
    }
}