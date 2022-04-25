<?php
namespace fwe\console;

use fwe\base\RouteException;

class Application extends \fwe\base\Application {

	public $controllerNamespace = 'app\commands';
	public $runActionMethod = 'runWithEvent';
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
			$method = $this->runActionMethod;
			$ret = $action->$method($params);
			if($ret === false) {
				$this->isExitNow = true;
			}
			if($this->events > 0) $this->signalEvent(function() use($ret) {
				if($this->events <= 0) \Fwe::$base->exit();
			});
			return $ret;
		} catch(RouteException $e) {
			echo $e;
		}
	}
}
