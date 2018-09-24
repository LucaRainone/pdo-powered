<?php

namespace rain1\PDOPowered\Config;


abstract class AbstractConfig implements ConfigInterface
{

    protected $user = "";
    protected $password = "";
    protected $options = [];

    public function getUser(): string
    {
        return $this->user;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function getOptions(): array
    {
        return $this->options;
    }
}