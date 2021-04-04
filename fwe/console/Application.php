<?php
namespace fwe\console;

use fwe\base\RouteException;

class Application extends \fwe\base\Application {

	public $controllerNamespace = 'app\commands';

	public function init() {
		parent::init();

		$this->controllerMap['help'] = 'fwe\console\HelpController';
		$this->defaultRoute = 'help';
	}

	public function boot() {
		$route = $_SERVER['argv'][1] ?? '';
		if(strncmp($route, '--', 2)) {
			$i = 2;
		} else {
			$route = '';
			$i = 1;
		}
		$params = [];
		for(; $i < $_SERVER['argc']; $i ++) {
			if(preg_match('/^--([^\=]+)\=?(.*)$/', $_SERVER['argv'][$i], $matches)) {
				$params[$matches[1]] = $matches[2];
			}
		}

		try {
			$this->runAction($route, $params);
		} catch(RouteException $e) {
			echo $e;
		}
	}
}
