<?php
namespace fwe\base;

class InlineAction extends Action {

	/**
	 * @var string
	 */
	public $method;
	
	public function __construct(string $id, Controller $controller, string $method) {
		parent::__construct($id, $controller);

		$this->method = $method;
		$this->callback = [$controller, $this->method];
	}

}
