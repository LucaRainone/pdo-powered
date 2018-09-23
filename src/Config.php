<?php

namespace rain1\PDOPowered;


class Config
{

    private $dbname;
    private $type = "mysql";
    private $user = "root";
    private $pass = "root";
    private $host = "localhost";
    private $port = 3306;
    private $charset = "utf8";

    public function __construct($dbname, $user, $pass, $host, $port, $charset)
    {

        $this->dbname = $dbname;
        $this->user = $user;
        $this->pass = $pass;
        $this->host = $host;
        $this->port = $port;
        $this->charset = $charset;

    }

    public function getConnectionString()
    {
        return "{$this->type}:host={$this->host};port={$this->port};dbname={$this->dbname};charset={$this->charset}";
    }

    public function getUser()
    {
        return $this->user;
    }

    public function getPassword()
    {
        return $this->pass;
    }

}