<?php

class ProjetoException extends Exception {
	public $extraData = [];

	public function __construct($message, $code = 0, Exception $previous = null, $extraData = []) {
		parent::__construct($message, $code, $previous);
		$this->extraData = $extraData;
	}

	public function getExtraData() {
		return $this->extraData;
	}
}