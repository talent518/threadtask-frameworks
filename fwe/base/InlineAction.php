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
		
		$this->callback = [$this->controller, $this->method];
	}

}
