<?php

namespace rain1\PDOPowered\Param;

class ParamJSON implements ParamInterface
{

    private $json;

    public function __construct($object)
    {
        $this->json = json_encode($object);
    }

    public function getValue()
    {
        return $this->json;
    }

    public function getArguments()
    {
        return [\PDO::PARAM_STR];
    }

}