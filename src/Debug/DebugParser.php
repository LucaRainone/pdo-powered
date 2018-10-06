<?php

namespace rain1\PDOPowered\Debug;

class DebugParser
{
    private $_lastTime;
    private $_timeorigin;

    public function __construct()
    {
        $this->_timeorigin = microtime(true);
    }

    public function parseDebugInfo(...$args)
    {
        $type = array_shift($args);

        $method = 'extractInfo' . ucfirst($type);
        if (method_exists($this, $method)) {
            return $this->$method(...$args);
        }

        return [
            'time' => microtime(true),
            $type => json_encode($args)
        ];

    }

    public static function onParse(\Closure $closure)
    {
        $instance = new static();
        return function (...$args) use ($instance, $closure) {
            $info = $instance->parseDebugInfo(...$args);
            if ($info)
                call_user_func($closure, $info);
        };
    }

    public function extractInfoBeforeQuery()
    {
        $this->_lastTime = microtime();
    }

    public function extractInfoQuery(\PDOStatement $stmt, string $query, $params)
    {

        return [
            'time' => microtime(true),
            'query' => [
                'executionTime' => $this->_time(),
                'original' => $query,
                'params' => $params,
                'demo' => $this->_buildQuery($query, $params)
            ],
            'stmt' => $stmt
        ];
    }

    private function _convertMicrotime($t1)
    {
        list($usec, $sec) = explode(' ', $t1);
        return ((float)$usec + (float)$sec);
    }

    private function _time()
    {
        if (is_null($this->_lastTime))
            return 0;
        return $this->_convertMicrotime(microtime()) - $this->_convertMicrotime($this->_lastTime);
    }

    private function _buildQuery(string $query, $params)
    {

        if (!is_array($params) || count($params) === 0)
            return $query;

        if (json_encode(array_keys($params)) === json_encode(range(0, count($params) - 1, 1))) {
            return $this->_parseQuestionMarkQuery($query, $params);
        }

        return str_replace(
            array_map(function ($param) {
                return ":$param";
            }, array_keys($params)),
            array_map(function ($value) {
                return $this->_paramToString($value);
            }, array_values($params))
            , $query);
    }

    private function _parseQuestionMarkQuery(string $query, array $params)
    {

        if (substr_count($query, "?") === count($params)) {
            foreach ($params as $param) {
                $query = preg_replace('/\?/', $this->_paramToString($param), $query, 1);
            }
            return $query;
        }
        // not possible to parse because there are "?" inside string
        return $this->_parseComplexQuery($query, $params);
    }

    private function _parseComplexQuery(string $query, array $params)
    {
        $strlen = strlen($query);
        $aposOpen = false;
        $quoteOpen = false;
        $final = "";
        for ($i = 0; $i < $strlen; $i++) {
            $char = $query{$i};
            $final .= $char;
            if ($char === "'") {
                $aposOpen = !$aposOpen;
                continue;
            }

            if ($char === '"') {
                $quoteOpen = !$quoteOpen;
                continue;
            }

            if ($char === '\\') {
                $i++;
                $final .= $query{$i};
                continue;
            }
            if ($char === "?" && !$aposOpen && !$quoteOpen) {
                $final = substr($final, 0, -1) . $this->_paramToString(array_shift($params));
            }
        }
        return $final;
    }

    private function _paramToString($value)
    {

        if ($value instanceof \rain1\PDOPowered\Param\ParamInterface)
            return $this->_paramToString($value->getValue());
        else if (is_object($value) && method_exists($value, "__toString"))
            return $this->_paramToString($value->__toString());
        else
            return is_int($value) ? $value : "'" . str_replace("'", "\\'", $value) . "'";

    }

}