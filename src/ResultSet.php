<?php

namespace rain1\PDOPowered;

use ReturnTypeWillChange;

/**
 * Class ResultSet
 * @package rain1\PDOPowered
 */
class ResultSet implements \IteratorAggregate
{
    private $statement;

    #[ReturnTypeWillChange] public function getIterator()
    {
        return $this->statement;
    }


    public function __construct(\PDOStatement $PDOStatement)
    {
        $this->statement = $PDOStatement;

    }

    public function getPDOStatement(): \PDOStatement
    {
        return $this->statement;
    }

    public function fetch($fetch_style = null, $cursor_orientation = \PDO::FETCH_ORI_NEXT, $cursor_offset = 0)
    {
        return $this->statement->fetch($fetch_style ?? \PDO::FETCH_DEFAULT, $cursor_orientation, $cursor_offset);
    }

    public function rowCount(): int
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

    public function fetchObjects($class_name = "\\stdClass", array $ctor_args = array()): array
    {
        $rows = [];
        while (($_row = $this->statement->fetchObject($class_name, $ctor_args)))
            $rows[] = $_row;

        return $rows;
    }

    public function closeCursor(): bool
    {
        return $this->statement->closeCursor();
    }

    public function debugDumpParams(): bool|string
    {
        ob_start();
        $this->statement->debugDumpParams();
        return ob_get_clean();
    }


}