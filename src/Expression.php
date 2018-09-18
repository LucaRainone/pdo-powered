<?php

namespace rain1\EasyDb;

class Expression {

	private $expression;

	public function __construct($expression) {
		$this->expression = $expression;
	}

	public function get() {
		return $this->expression;
	}

}