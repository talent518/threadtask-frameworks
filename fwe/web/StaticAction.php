<?php
namespace fwe\web;

class StaticAction {
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
}
