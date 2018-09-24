<?php

namespace rain1\PDOPowered\Config;


class DSN extends AbstractConfig
{
    private $dsn;

    public function __construct($dsn, $user, $password)
    {
        $this->dsn = $dsn;
        $this->user = $user;
        $this->password = $password;
    }

    public function setOptions(array $options)
    {
        $this->options = $options;
    }

    public function getConnectionString(): string
    {
        return $this->dsn;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

}