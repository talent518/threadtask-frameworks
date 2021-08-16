<?php

namespace fwe\web;

use fwe\base\IAction;

class StaticAction implements IAction {

	public $id, $controller, $callback, $funcName, $route, $params;

	public $prefix, $path, $file;

	public function beforeAction() {
		return true;
	}

	public function afterAction() {
	}

	final public function runWithEvent() {
		$this->run();
	}

	final public function run() {
		$this->params['request']->getResponse()->sendFile($this->file);
	}

	public function getRoute() {
	}

	public function getParams() {
	}

	public function free() {
	}

}
