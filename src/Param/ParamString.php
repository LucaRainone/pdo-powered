<?php

namespace rain1\PDOPowered\Param;

class ParamString implements ParamInterface
{

    private $string;

    public function __construct($value)
    {
        $this->string = (string)$value;
    }

    public function getValue()
    {
        return $this->string;
    }

    public function getArguments()
    {
        return [\PDO::PARAM_STR];
    }

}