<?php
namespace fwe\base;

abstract class Action {
	/**
	 * @var string
	 */
	public $id;
	
	/**
	 * @var Controller
	 */
	public $controller;
	
	public function __construct(string $id, Controller $controller) {
		$this->id = $id;
		$this->controller = $controller;
	}
	
	public function init() {
		$this->controller->actionObjects[$this->id] = $this;
	}
	
	public function beforeAction(Action $action) {
		return true;
	}

	public function afterAction(Action $action) {
	}

	abstract public function run(array $params);
}