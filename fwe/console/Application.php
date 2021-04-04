<?php
namespace fwe\console;

use fwe\base\RouteException;

class Application extends \fwe\base\Application {
	public $controllerNamespace = 'app\commands';
	
	public function init() {
		parent::init();
		
		$this->controllerMap['help'] = 'fwe\console\HelpController';
	}
	
	public function boot() {
		// var_dump($_SERVER, $this, \Fwe::$aliases->all(), \Fwe::$classMap->all(), \Fwe::$classAlias->all());
		
		$route = $_SERVER['argv'][1] ?? '';
		$n = count($_SERVER['argv']);
		$params = [];
		for($i = 2; $i<$n; $i++) {
			$arg = $_SERVER['argv'][$i];
			echo "$arg\n";
		}
		
		try {
			$this->runAction($route, $params);
		} catch(RouteException $e) {
			echo $e;
		}
	}
}
