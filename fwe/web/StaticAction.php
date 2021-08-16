<?php

namespace fwe\web;

use fwe\base\IAction;

class StaticAction implements IAction {

	public $id, $controller, $callback, $funcName, $route;

	public $prefix, $path, $file;

	public function beforeAction(array $params = []): bool {
		return true;
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
