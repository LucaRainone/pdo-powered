<?php

namespace rain1\PDOPowered;

use rain1\PDOPowered\Config\ConfigInterface;
use rain1\PDOPowered\Config\DSN;
use rain1\PDOPowered\Param\ParamInterface;

class PDOPowered
{

    public static int $MAX_TRY_CONNECTION = 3;
    private ?\PDO $pdo = null;
    private bool $_isConnected = false;
    private int $connectionTry = 0;
    private ConfigInterface $dbConfig;

    private array $callbacks = [
        'connectFailure' => [],
        'connect' => [],
        'debug' => []
    ];

    public function __construct(ConfigInterface $dbConfig)
    {
        $this->dbConfig = $dbConfig;
    }

    public static function buildFromPDOInstance(\PDO $pdo):PDOPowered
    {
        $config = new DSN("","","");
        $instance = new static($config);
        $instance->pdo = $pdo;
        $instance->_isConnected = true;
        return $instance;
    }

    public function onConnectionFailure($callback): int
    {
        return $this->_addListener('connectFailure', $callback);
    }

    /**
     * @throws Exception
     */
    private function _addListener($eventName, $callback): int
    {
        if (!is_callable($callback))
            throw new Exception("expected a callable on on* methods");

        $this->callbacks[$eventName][] = $callback;
        return count($this->callbacks[$eventName]);
    }

    public function removeConnectionFailureListener($idListener)
    {
        $this->_removeListener('connectFailure', $idListener);
    }

    private function _removeListener($namespace, $idListener): void
    {
        $index = $idListener - 1;
        if (isset($this->callbacks[$namespace][$index]))
            unset($this->callbacks[$namespace][$index]);
    }

    public function onDebug($callback): int
    {
        return $this->_addListener('debug', $callback);
    }

    public function removeDebugListener($idListener): void
    {
        $this->_removeListener('debug', $idListener);
    }

    public function removeOnConnectListener($idListener): void
    {
        $this->_removeListener('connect', $idListener);
    }

    /**
     * @throws Exception
     */
    public function query($query, $params = []): ResultSet
    {

        $stmt = $this->getPDO()->prepare($query);

        if ($stmt === false)
            throw new Exception("Cannot prepare query " . json_encode($query));

        $questionMark = (count($params) && key($params) === 0);
        foreach ($params as $index => $param)
            $this->_bindValue($stmt, $questionMark ? $index + 1 : $index, $param);

        $this->debug("beforeQuery");
        $this->debug("query", $stmt, $query, $params);

        $res = $stmt->execute();

        $this->debug("afterQuery");

        if (!$res)
            throw new Exception("Query Error ({$stmt->errorCode()}: " . json_encode($stmt->errorInfo()));

        return new ResultSet($stmt);
    }

    /**
     * @throws \Exception
     */
    protected function getPDO(): \PDO
    {
        return $this->pdo ?: $this->connectAndFetchPDOInstance();
    }

    /**
     * @throws \Exception
     */
    private function connectAndFetchPDOInstance(): ?\PDO
    {
        try {
            $this->pdo = new \PDO($this->dbConfig->getConnectionString(), $this->dbConfig->getUser(), $this->dbConfig->getPassword(), $this->dbConfig->getOptions());
        } catch (\Exception $e) {
            if (++$this->connectionTry < self::$MAX_TRY_CONNECTION) {
                $this->trigger("connectFailure", $this->connectionTry, $e);
                return $this->connectAndFetchPDOInstance();
            }

            $this->hideSensibileInfos();
            throw $e;
        }
        unset($this->dbConfig);
        $this->_isConnected = true;
        $this->pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
        $this->trigger("connect", $this);
        return $this->pdo;
    }

    private function trigger($eventName, ...$args): void
    {
        foreach ($this->callbacks[$eventName] as $callback)
            call_user_func_array($callback, $args);

    }

    private function hideSensibileInfos(): void
    {
        unset($this->dbConfig);
    }

    private function _bindValue(\PDOStatement $stmt, $name, $value): void
    {

        if ($value instanceof ParamInterface)
            $stmt->bindValue($name, $value->getValue(), ...$value->getArguments());
        else
            $stmt->bindValue($name, $value, is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
    }

    private function debug(): void
    {
        $this->trigger("debug", ...func_get_args());
    }

    /**
     * @throws \Exception
     */
    public function prepare(...$args): \PDOStatement
    {
        $pdo = $this->getPDO();
        return call_user_func_array([$pdo, "prepare"], $args);
    }

    /**
     * @throws Exception
     */
    public function insert($table, array $params): bool|string
    {
        return $this->_insertOrInsertOnDuplicateKey($table, $params);
    }

    /**
     * @throws Exception
     * @throws \Exception
     */
    private function _insertOrInsertOnDuplicateKey($table, array $params, $withOnDuplicateKey = false): bool|string
    {
        $db = $this->getPDO();

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

        $params = $this->_filterParams($params);

        $this->query($qry, $params);

        return $db->lastInsertId();
    }

    /**
     * @throws Exception
     */
    public function insertOnDuplicateKeyUpdate($table, array $params): bool|string
    {
        return $this->_insertOrInsertOnDuplicateKey($table, $params, true);
    }

    /**
     * @throws Exception
     */
    public function delete($table, $where): int
    {

        $parts = [];

        foreach ($where as $field => $value)
            if ($value instanceof Expression)
                $parts[] = "$field = {$value->get()}";
            else
                $parts[] = "$field = :$field";

        $conditionPart = implode(" AND ", $parts);

        $params = $this->_filterParams($where);

        return $this->query("DELETE FROM $table WHERE $conditionPart", $params)->rowCount();

    }

    public function update($table, array $array, array $where): int
    {
        $parts = [];
        $params = [];
        foreach ($array as $field => $value) {
            if($value instanceof Expression) {
                $parts[] = "$field = {$value->get()}";
            }else {
                $varname = "PDOPOW_UP_$field";
                $parts[] =  "$field = :$varname";
                $params[$varname] = $value;
            }
        }

        $setPart = implode(", ", $parts);

        $parts = [];
        foreach ($where as $field => $value) {
            if (!($value instanceof Expression)) {
                $varname = "PDOPOW_WH_$field";
                $parts[] = "$field = :$varname";
                $params[$varname] = $value;
            }
        }

        $conditionPart = implode(" AND ", $parts);

        return $this->query("UPDATE $table SET $setPart WHERE $conditionPart",$params)->rowCount();
    }

    /**
     * @throws \Exception
     */
    public function beginTransaction(): void
    {
        $db = $this->getPDO();
        $this->debug("beginTransaction");
        $db->beginTransaction();
    }

    /**
     * @throws \Exception
     */
    public function rollbackTransaction(): void
    {
        $db = $this->getPDO();
        $this->debug("rollbackTransaction");
        $db->rollBack();
    }

    /**
     * @throws \Exception
     */
    public function commitTransaction(): void
    {
        $db = $this->getPDO();
        $this->debug("commitTransaction");
        $db->commit();
    }

    public function isConnected(): bool
    {
        return $this->_isConnected;
    }

    public function setPDOAttribute($attributeName, $attributeValue): void
    {
        if ($this->pdo instanceof \PDO)
            $this->pdo->setAttribute($attributeName, $attributeValue);
        else
            $this->onConnect(function () use ($attributeName, $attributeValue) {
                $this->setPDOAttribute($attributeName, $attributeValue);
            });
    }

    /**
     * @throws Exception
     */
    public function onConnect(callable $callback): ?int
    {
        if ($this->_isConnected)
            $callback($this);
        else
            return $this->_addListener('connect', $callback);

        return null;
    }

    private function _filterParams($where): array
    {
        return array_filter($where, function($param) {
            return !($param instanceof Expression);
        });
    }
}