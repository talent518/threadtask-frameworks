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
		if(\Fwe::$app->boot()) {
			$pidFile = \Fwe::getAlias('@app/runtime/' . $name . '.pid');
			$pidPath = dirname($pidFile);
			is_dir($pidPath) or mkdir($pidPath, 0755, true);
			file_put_contents($pidFile, posix_getpid());
		}
	}

	/**
	 * 停止Web服务器
	 *
	 * @param string $name
	 */
	public function actionStop(string $name = 'web') {
		$pidFile = \Fwe::getAlias('@app/runtime/' . $name . '.pid');
		if(! is_readable($pidFile)) {
			echo "NO\n";
			return;
		}

		$pid = file_get_contents($pidFile);
		if($pid <= 0 || trim(file_get_contents("/proc/$pid/comm")) != 'threadtask') {
			if(!is_dir("/proc/$pid/"))
				@unlink($pidFile);
			echo "NO: $pid\n";
			return;
		}

		if(posix_kill($pid, SIGINT)) {
			@unlink($pidFile);
			echo "OK\n";
		} else {
			echo "ERR\n";
		}
	}
	
	/**
	 * 重启Web服务器
	 *
	 * @param string $name
	 */
	public function actionReload(string $name = 'web') {
		$pidFile = \Fwe::getAlias('@app/runtime/' . $name . '.pid');
		if(! is_readable($pidFile)) {
			echo "NO\n";
			return;
		}

		$pid = file_get_contents($pidFile);
		if($pid <= 0 || trim(file_get_contents("/proc/$pid/comm")) !== 'threadtask') {
			if(!is_dir("/proc/$pid/"))
				@unlink($pidFile);
			echo "NO: $pid\n";
			return;
		}
		
		if(posix_kill($pid, SIGUSR1))
			echo "OK\n";
		else
			echo "ERR\n";
	}
}
