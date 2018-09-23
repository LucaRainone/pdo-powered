<?php

namespace rain1\PDOPowered\Param;

class ParamInt implements ParamInterface
{

    private $int;

    public function __construct($value)
    {
        $this->int = (int)$value;
    }

    public function getValue()
    {
        return $this->int;
    }

    public function getArguments()
    {
        return [\PDO::PARAM_INT];
    }

}