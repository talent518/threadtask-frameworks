<?php
namespace fwe\console;

use fwe\base\Action;
use fwe\utils\FileHelper;

class ServeController extends Controller {
	
	public function beforeAction(Action $action, array &$params = []): bool {
		// 强制覆盖父类
		restore_error_handler();
		restore_exception_handler();
		\Fwe::$base = new \EventBase();
		return true;
	}
	
	public function afterAction(Action $action, array &$params = []) {
		// 强制覆盖父类
	}
	
	public function afterActionIndex() {
		$this->module->controllerObjects = [];
		$this->module = null;
		$this->actionObjects = [];
		\Fwe::$config->remove('main');
	}

	/**
	 * 启动Web服务器
	 * 
	 * @param string $name 配置文件名
	 * @param string $host 监听IP
	 * @param int $port 监听端口
	 * @param int $backlog 最大连接队列
	 * @param int $maxAccepts 最大接受连接线程数
	 * @param int $maxThreads 最大工作线程数
	 * @param int $keepAlive 保持连接秒数
	 * @param int $logLevel 日志级别
	 * @param int $traceLevel 跟踪层次
	 * @param int $logSize 日志文件大小的最大值
	 * @param int $logMax 日志文件的最大份数
	 * @param string $logFormat 日志文件名的格式(sprintf)
	 * @param bool $isToFile 是否把上传的文件保存到临时目录中
	 * @return mixed
	 */
	public function actionIndex(string $name = 'web', ?string $host = null, ?int $port = null, ?int $backlog = null, ?int $maxAccepts = null, ?int $maxThreads = null, ?int $keepAlive = null, ?int $logLevel = null, ?int $traceLevel = null, ?int $logSize = null, ?int $logMax = null, ?string $logFormat = null, ?bool $isToFile = null) {
		\Fwe::$app->logAll();
		redefine('THREAD_TASK_NAME', $name);
		\Fwe::$name = $name;
		\Fwe::$names = [];
		$ret = \Fwe::createObject(\Fwe::$config->getOrSet(\Fwe::$name, (function () use($host, $port, $backlog, $maxAccepts, $maxThreads, $keepAlive, $logLevel, $traceLevel, $logSize, $logMax, $logFormat, $isToFile) {
			$cfg = include \Fwe::getAlias('@app/config/' . \Fwe::$name . '.php');
			if($host !== null) $cfg['host'] = $host;
			if($port !== null) $cfg['port'] = $port;
			if($backlog !== null) $cfg['backlog'] = $backlog;
			if($maxAccepts !== null) $cfg['maxAccepts'] = $maxAccepts;
			if($maxThreads !== null) $cfg['maxThreads'] = $maxThreads;
			if($keepAlive !== null) $cfg['keepAlive'] = $keepAlive;
			if($logLevel !== null) $cfg['logLevel'] = $logLevel;
			if($traceLevel !== null) $cfg['traceLevel'] = $traceLevel;
			if($logSize !== null) $cfg['logSize'] = $logSize;
			if($logMax !== null) $cfg['logMax'] = $logMax;
			if($logFormat !== null) $cfg['logFormat'] = $logFormat;
			if($isToFile !== null) $cfg['isToFile'] = $isToFile;
			return $cfg;
		})->bindTo(null)))->boot();
		if($ret) {
			$pidFile = \Fwe::getAlias('@app/runtime/' . $name . '.pid');
			$pid = @file_get_contents($pidFile);
			$version = PHP_VERSION;
			if($pid == posix_getpid()) {
				echo "Service restart, PHP version $version\n";
				\Fwe::$app->info("Restart, PHP version $version", 'service');
			} else {
				FileHelper::mkdir(dirname($pidFile)) and file_put_contents($pidFile, posix_getpid());
				echo "Service started, PHP version $version\n";
				\Fwe::$app->info("Started, PHP version $version", 'service');
			}
			
			register_shutdown_function((function($file, $app, $t) {
				if(!in_array($app->exitSig(), [SIGUSR1, SIGUSR2])) {
					@unlink($file);
				}
				$t = round(microtime(true) - $t, 6);
				echo "Service stopped, run time is $t seconds\n";
				$app->info("Stopped, run time is $t seconds", 'service');
			})->bindTo(null), $pidFile, \Fwe::$app, microtime(true));
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

	const KEYS = ['Y', 'm', 'd', 'w', 'H', 'i', 's'];

	/**
	 * 计划任务
	 *
	 * @param bool $isDebug
	 */
	public function actionCrontab(bool $isDebug = false) {
		$cfgFile = \Fwe::getAlias('@app/config/crontab.ini');
		$timeFile = \Fwe::getAlias('@app/runtime/crontab.time');
		$lockFile = \Fwe::getAlias('@app/runtime/crontab.lock');

		if(!is_file($cfgFile)) {
			echo "ini file not exists\n";
			return;
		}

		if(is_file($lockFile)) {
			echo "running...\n";
			return;
		}
		touch($lockFile);

		share_var_init();

		$cfgs = parse_ini_file($cfgFile, true, INI_SCANNER_RAW);
		if($cfgs === false) {
			echo error_get_last()['message'];
			return;
		}

		$this->cfg_vars($cfgs);

		$running = true;
		foreach($cfgs as $key => &$cfg) {
			if(!isset($cfg['type'])) {
				echo "No type parameter at key '$key'\n";
				unset($cfgs[$key]);
				$running = false;
				continue;
			}
			
			$cfg['type'] = strtolower($cfg['type']);
			if(empty($cfg['file'])) $cfg['file'] = INFILE;
			
			switch($cfg['type']) {
				case 'php':
				case 'once':
				case 'script':
				case 'daemon':
					if($cfg['type'] === 'once') $n = 1;
					else $n = max(1, $cfg['count'] ?? 1);
					
					for($i=0; $i<$n; $i++) {
						if(!$isDebug && !create_task($key, $cfg['file'], $cfg['args']??[], $cfg['logfile']??null, $cfg['logmode']??'ab')) {
							echo "create task $key failure\n";
						}
					}
					unset($cfgs[$key]);
				case 'cron':
					if($cfg['type'] === 'cron' && !isset($cfg['cron'])) {
						echo "No cron parameter at key '$key'\n";
						unset($cfgs[$key]);
						$running = false;
					}
					break;
				default:
					echo "unknown type '{$cfg['type']}' at key '$key'\n";
					unset($cfgs[$key]);
					$running = false;
					break;
			}
		}
		unset($cfg);
		if(!$running) return;

		$crons = [];
		$times = (($t = @file_get_contents($timeFile))?(json_decode($t, true)?:[]):[]);
		$time = microtime(true);
		while(\Fwe::$app->isRunning()) {
			isset($ctime) and ($t = 1000000 - 1000000 * (microtime(true) - $time)) >= 0 and usleep($t);
			
			$time = microtime(true);
			$ctime = (int) $time;
			$a1 = array_combine(static::KEYS, explode(' ', date('Y m d w H i s', $ctime)));
			
			if($isDebug) {
				$isHas = false;
				
				$t = implode(' ', $a1);
				echo "$t\n";
			}

			foreach($cfgs as $key => &$cfg) {
				if(isset($crons[$key])) {
					if(task_is_run($crons[$key])) continue;
					else unset($crons[$key]);
				}
				
				if(!isset($times[$key])) {
					$times[$key] = $ctime;
					continue;
				}

				$a0 = preg_split('/\s/', '* ' . $cfg['cron']);
				if(count($a0) != count(static::KEYS)) {
					echo "Unknown cron '{$cfg['cron']}' parameter size at key '$key'\n";
					continue;
				}

				$mtime = $times[$key];
				
				$a0 = array_combine(static::KEYS, $a0);

				$isNext = true;
				foreach($a0 as $k=>$v) {
					if($v === '*') continue;
					
					if(!preg_match('/^(\*\/)?\d+$/', $v)) {
						echo "Error cron parameter '$k' => '$v' at key '$key'\n";
						continue;
					}
					
					if(!strncmp($v, '*/', 2)) {
						$v = max(1, (int) substr($v, 2));

						switch($k) {
							case 'm':
								@list($Y, $m) = explode(' ', date('Y m', $mtime));
								$t = ($a1['Y'] - $Y) * 12 + $a1['m'] - $m;
								break;
							case 'd':
								$t = ($ctime - $mtime) / 86400;
								break;
							case 'w':
								$t = ($ctime - $mtime) / (7 * 86400);
								break;
							case 'H':
								$t = ($ctime - $mtime) / 3600;
								break;
							case 'i':
								$t = ($ctime - $mtime) / 60;
								break;
							case 's':
								$t = $ctime - $mtime;
								break;
							default:
								$t = 0;
								break;
						}
						
						if($t < $v) {
							$isNext = false;
							break;
						}
					} else {
						if($v != $a1[$k]) {
							$isNext = false;
							break;
						}
					}
				}

				if($isNext) {
					if($isDebug) {
						$isHas = true;
						
						echo "-------------------------\n";
						
						var_dump($key, $cfg);
					}

					if(!$isDebug && !create_task($key, $cfg['file'], $cfg['args']??[], $cfg['logfile']??null, $cfg['logmode']??'ab', $crons[$key])) {
						echo "create task $key failure\n";
					} else {
						$times[$key] = $ctime;
					}
				}
			}
			unset($cfg);
			
			if($isDebug && $isHas) echo "-------------------------\n";
		}

		share_var_destory();

		file_put_contents($timeFile, json_encode($times, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));

		unlink($lockFile);

		if(\Fwe::$app->exitSig() == SIGUSR1) {
			$s = date('H:i:s');
			echo "[$s] RESTART\n";
		}
	}

	protected function cfg_vars(array &$vars) {
		foreach($vars as &$var) {
			if(is_array($var)) $this->cfg_vars($var);
			else $var = preg_replace_callback('/\$\{?([a-z_][0-9a-z_]*)\}?/i', function($matches) {
				return getenv($matches[1]);
			}, $var);
		}
	}
}
