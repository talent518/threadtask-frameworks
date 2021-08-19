<?php
namespace fwe\console;

class ServeController extends Controller {

	/**
	 * 启动Web服务器
	 *
	 * @param array $__params__
	 * @param string $route
	 */
	public function actionIndex(string $name = 'web', int $maxThreads = 16, int $backlog = 128) {
		\Fwe::$name = $name;
		$config = \Fwe::$config->getOrSet(\Fwe::$name, function () use($maxThreads, $backlog) {
			$cfg = include \Fwe::getAlias('@app/config/' . \Fwe::$name . '.php');
			$cfg['maxThreads'] = $maxThreads;
			$cfg['backlog'] = $backlog;
			return $cfg;
		});
		\Fwe::$app = \Fwe::createObject($config);
		unset($config);
		$ret = \Fwe::$app->boot();
		if($ret) {
			$pidFile = \Fwe::getAlias('@app/runtime/' . $name . '.pid');
			$pidPath = dirname($pidFile);
			is_dir($pidPath) or mkdir($pidPath, 0755, true);
			$pid = @file_get_contents($pidFile);
			if($pid == posix_getpid()) {
				echo "Service restart\n";
			} else {
				file_put_contents($pidFile, posix_getpid());
				echo "Service started\n";
			}
			register_shutdown_function(function($file) {
				@unlink($file);
				echo "Service stopped\n";
			}, $pidFile);
		}
		return $ret;
	}

	/**
	 * 停止Web服务器
	 *
	 * @param string $name
	 */
	public function actionStop(string $name = 'web') {
		$this->kill($name, SIGINT);
	}
	
	/**
	 * 重启Web服务器
	 *
	 * @param string $name
	 */
	public function actionReload(string $name = 'web') {
		$this->kill($name, SIGUSR1);
	}

	protected function kill(string $name, int $sig) {
		$pidFile = \Fwe::getAlias('@app/runtime/' . $name . '.pid');

		if(!is_readable($pidFile) || ($pid = file_get_contents($pidFile)) <= 0 || !($comm = @file_get_contents("/proc/$pid/comm")) || trim($comm) != 'threadtask') {
			echo "NO\n";
			return;
		}
		
		if(posix_kill($pid, $sig))
			echo "OK\n";
		else
			echo "ERR\n";
	}
}
