<?php

namespace rain1\EasyDb;


class DbConfig {

	private $dbname;
	private $type    = "mysql";
	private $user    = "root";
	private $pass    = "root";
	private $host    = "localhost";
	private $port    = 3306;
	private $charset = "utf8";

	public function __construct($dbname, $user, $pass, $host, $port, $charset) {

		foreach (["dbname", "user", "pass", "host", "port", "charset"] as $key)
			$this->$key = $$key;

	}

	public function getConnectionString() {
		return "{$this->type}:host={$this->host};port={$this->port};dbname={$this->dbname};charset={$this->charset}";
	}

	public function getUser() {
		return $this->user;
	}

	public function getPassword() {
		return $this->pass;
	}

}