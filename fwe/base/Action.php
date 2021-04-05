<?php
namespace fwe\base;

use fwe\traits\MethodProperty;

/**
 * @author abao
 * @property-read string $route
 */
abstract class Action {
	use MethodProperty;
	
	/**
	 * @var string
	 */
	public $id;
	
	/**
	 * @var Controller
	 */
	public $controller;
	
	/**
	 * @var string
	 */
	private $_route;
	
	public function __construct(string $id, Controller $controller) {
		$this->id = $id;
		$this->controller = $controller;
		$this->_route = "{$controller->route}{$id}";
	}
	
	public function getRoute() {
		return $this->_route;
	}
	
	public function init() {
		$this->controller->actionObjects[$this->id] = $this;
	}
	
	public function beforeAction() {
		return $this->controller->beforeAction($this);
	}

	public function afterAction() {
		$this->controller->afterAction($this);
	}
	
	final public function runWithEvent(array $params) {
		if($this->beforeAction()) {
			$ret = $this->run($params);
			$this->afterAction();
			
			return $ret;
		}
	}

	abstract public function run(array $params);
}