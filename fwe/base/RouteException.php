<?php
namespace fwe\base;

class RouteException extends Exception {
	private $_route;
	
	public function __construct(string $route, string $message) {
		$this->_route = $route;
		parent::__construct($message);
	}
	
	public function getRoute() {
		return $this->_route;
	}
}