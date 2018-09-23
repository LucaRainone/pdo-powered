<?php

namespace rain1\PDOPowered\Param;

class ParamNative implements ParamInterface
{

    private $value;
    private $pdoParamConstant;

    public function __construct($value, $pdoParamConstant)
    {
        $this->value = $value;
        $this->pdoParamConstant = $pdoParamConstant;
    }

    public function getValue()
    {
        return $this->value;
    }

    public function getArguments()
    {
        return [$this->pdoParamConstant];
    }

}