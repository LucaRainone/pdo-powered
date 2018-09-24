<?php

namespace rain1\PDOPowered\Config;


class Config extends AbstractConfig
{

    private $dbname;
    private $type;
    private $host = "localhost";
    private $port = 3306;
    private $charset = "utf8";

    public function __construct($type, $user, $password, $host, $port, $dbname, $charset, $options = [])
    {
        $this->type = $type;
        $this->dbname = $dbname;
        $this->user = $user;
        $this->password = $password;
        $this->host = $host;
        $this->port = $port;
        $this->charset = $charset;
        $this->options = $options;

    }

    public function getConnectionString(): string
    {
        return "{$this->type}:host={$this->host};port={$this->port};dbname={$this->dbname};charset={$this->charset}";
    }

}