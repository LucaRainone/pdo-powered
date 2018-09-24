<?php

namespace rain1\PDOPowered\Config;

interface ConfigInterface  {
    public function getConnectionString():string;
    public function getUser():string;
    public function getPassword():string;
    public function getOptions():array;
}