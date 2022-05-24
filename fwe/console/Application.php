<?php
namespace fwe\console;

use fwe\base\Action;
use fwe\base\RouteException;

class Application extends \fwe\base\Application {

	public $controllerNamespace = 'app\commands';
	public $signalTimeout = 0.01;
	public $isExitNow = false;

	public function init() {
		parent::init();

		$this->controllerMap['help'] = 'fwe\console\HelpController';
		$this->controllerMap['serve'] = 'fwe\console\ServeController';
		$this->controllerMap['generator'] = 'fwe\console\GeneratorController';
		$this->defaultRoute = 'help';
	}
	
	public function signalHandler(int $sig) {
		parent::signalHandler($sig);
		
		if(!$this->_running && $this->isExitNow) {
			\Fwe::$base->exit();
		}
	}
	
	public function beforeAction(Action $action, array &$params = []): bool {
		$this->logInit();

		return parent::beforeAction($action, $params);
	}
	
	public function afterAction(Action $action, array &$params = []) {
		parent::afterAction($action, $params);

		if($this->events > 0) {
			$this->signalEvent(function() {
				if($this->events <= 0) \Fwe::$base->exit();
			});
		} else {
			\Fwe::$base->exit();
		}
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
			$matches = [];
			if(preg_match('/^--([^\=]+)\=?(.*)$/', $param, $matches)) {
				$params[$matches[1]] = $matches[2];
			} else {
				$params[] = $param;
			}
		}
		try {
			$action = $this->getAction($route, $params);
			$ret = $action->runWithEvent($params);
			if($ret === false) {
				$this->isExitNow = true;
			}
			return $ret;
		} catch(RouteException $e) {
			$e = $e->getMessage();
			echo "$e\n";
		}
	}
}
