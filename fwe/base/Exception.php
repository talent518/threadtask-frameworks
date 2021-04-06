<?php
namespace fwe\base;

class Exception extends \Exception {
	/**
	 * @var mixed
	 */
	private $_data;
	
	public function __construct(string $message, $data = null) {
		parent::__construct($message);
		$this->_data = $data;
	}
	
	public function getData() {
		return $this->_data;
	}
	
	public function __toString() {
		if($this->_data) {
			$data = var_export($this->_data, true);
			return "Data: $data\n" . parent::__toString();
		} else {
			return parent::__toString();
		}
	}
}