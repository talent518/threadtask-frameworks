<?php
namespace fwe\base;

class InlineAction extends Action {

	/**
	 * @var string
	 */
	public $method;
	
	public function __construct(string $id, string $method, Controller $controller, array $params) {
		$this->method = $method;
		
		parent::__construct($id, $controller, $params);
		
		$this->callback = [$this->controller, $this->method];
	}

}
