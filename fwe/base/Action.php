<?php
namespace fwe\base;

use fwe\traits\MethodProperty;

/**
 * @author abao
 * @property-read string $route
 */
class Action implements IAction {
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
	 * @var callable
	 */
	public $callback;
	
	/**
	 * @var string
	 */
	public $funcName;
	
	/**
	 * @var string
	 */
	private $_route;
	
	/**
	 * @var array
	 */
	private $_params;
	
	public function __construct(string $id, Controller $controller, array $params) {
		$this->id = $id;
		$this->controller = $controller;
		$this->_route = "{$controller->route}{$id}";
		$this->callback = [$this, 'runWithParams'];
		$this->_params = $params;
	}
	
	public function getRoute() {
		return $this->_route;
	}
	
	public function getParams() {
		return $this->_params;
	}
	
	public function init() {
		$this->controller->actionObjects[$this->id] = $this;
		if(is_array($this->callback)) {
			$class = (is_string($this->callback[0]) ? $this->callback[0] : get_class($this->callback[0]));
			$this->funcName = "{$class}::{$this->callback[1]}";
		} elseif(is_string($this->callback)) {
			$this->funcName = $this->callback;
		} elseif(is_object($this->callback) && ! $this->callback instanceof \Closure) {
			$class = get_class($this->callback);
			$this->funcName = "{$class}::__invoke";
		}
	}
	
	public function free() {
		unset($this->controller->actionObjects[$this->id]);
	}
	
	public function beforeAction() {
		return $this->controller->beforeAction($this);
	}

	public function afterAction() {
		$this->controller->afterAction($this);
	}
	
	final public function runWithEvent(array $params = []) {
		if($this->beforeAction()) {
			$ret = $this->run($params);
			$this->afterAction();
			
			return $ret;
		}
	}

	final public function run(array $params = []) {
		return \Fwe::invoke($this->callback, $this->_params + $params + ['actionID'=>$this->id], $this->funcName);
	}

}
