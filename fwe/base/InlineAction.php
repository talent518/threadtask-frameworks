<?php
namespace fwe\base;

class InlineAction extends Action {
	/**
	 * @var string
	 */
	public $method;
	
	public function __construct(string $id, string $method, Controller $controller) {
		$this->method = $method;
		
		parent::__construct($id, $controller);
	}
	
	public function run(array $params) {
		$class = get_class($this->controller);
		return \Fwe::invoke([$this->controller, $this->method], $params, "{$class}::{$this->method}");
	}
}
