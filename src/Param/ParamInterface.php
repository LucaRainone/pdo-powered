<?php

namespace rain1\PDOPowered\Param;

interface ParamInterface
{
    public function getValue();

    public function getArguments();
}