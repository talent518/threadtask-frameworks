<?php
namespace fwe\console;

use fwe\base\RouteException;

class Application extends \fwe\base\Application {

	public $controllerNamespace = 'app\commands';
	public $runActionMethod = 'runWithEvent';

	public function init() {
		parent::init();

		$this->controllerMap['help'] = 'fwe\console\HelpController';
		$this->controllerMap['serve'] = 'fwe\console\ServeController';
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
			$param = $_SERVER['argv'][$i];
			if(preg_match('/^--([^\=]+)\=?(.*)$/', $param, $matches)) {
				$params[$matches[1]] = $matches[2];
			} else {
				$params[] = $param;
			}
		}
		$params['__params__'] = &$params;
		try {
			$action = $this->getAction($route, $params);
			$method = $this->runActionMethod;
			$action->$method(['actionID' => $action->id]);
		} catch(RouteException $e) {
			echo $e;
		}
	}
}
