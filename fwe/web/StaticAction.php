<?php

namespace fwe\web;

use fwe\base\IAction;

class StaticAction implements IAction {

	public $id, $controller, $callback, $funcName, $route;

	public $prefix, $path, $file;
	
	public function init() {
		\Fwe::debug(get_called_class(), $this->path, false);
	}
	
	public function __destruct() {
		\Fwe::debug(get_called_class(), $this->path, true);
	}
	
	public function beforeAction(array $params = []): bool {
		return $this->params['request']->bodylen === 0;
	}

	public function afterAction(array $params = []) {
	}

	final public function runWithEvent(array $params = []) {
		$this->run($params);
	}

	final public function run(array $params = []) {
		$this->params['request']->getResponse()->sendFile($this->file);
	}

	public function getRoute() {
	}

}
