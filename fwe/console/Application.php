<?php
namespace fwe\console;

class Application extends \fwe\base\Application {
	public $id, $name;
	
	public function __construct(string $id, string $name) {
		$this->id = $id;
		$this->name = $name;
		
		parent::__construct();
	}

	public function boot() {
		var_dump($_SERVER, $this, \Fwe::$aliases->all(), \Fwe::$classMap->all(), \Fwe::$classAlias->all());
	}
}
