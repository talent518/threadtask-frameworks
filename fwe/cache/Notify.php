<?php
namespace fwe\cache;

class Notify {
	protected $_key;
	
	public function __construct(string $key) {
		$this->_key = $key;
	}
}
