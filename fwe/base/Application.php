<?php
namespace fwe\base;

use fwe\curl\Boot;
use fwe\db\MySQLPool;
use fwe\db\RedisPool;
use fwe\validators\Validator;

/**
 * 应用基类
 * 
 * @method bool has(string $id)
 * @method array def(string $id)
 * @method object|null get(string $id, bool $isMake = true)
 * @method void set(string $id, $value, bool $isFull = true)
 * @method void remove(string $id)
 * @method array all(bool $isObject = true)
 */
abstract class Application extends Module {
	const LOG_ERROR = 0x01;
	const LOG_WARN = 0x02;
	const LOG_INFO = 0x04;
	const LOG_DEBUG = 0x08;
	const LOG_VERBOSE = 0x10;
	const LOG_ALL = 0x1f;
	const LOG_SKIP = 0x100;
	
	public $id, $name;
	public $events = 0; // EventBase中添加的事件数
	
	/**
	 * 默认加载的组件或模块
	 *
	 * @var array $ids
	 */
	public $bootstrap = [];
	
	public function __construct(string $id, string $name) {
		$this->id = $id;
		$this->name = $name;
		$this->extendObject = \Fwe::createObject(Component::class);
		
		parent::__construct($id);

		$this->setComponents([
			'curl' => Boot::class,
			'db' => MySQLPool::class,
			'redis' => RedisPool::class,
			'validator' => Validator::class,
		]);
		
		\Fwe::debug(get_called_class(), $this->id, false);
		
		$this->setBasePath('@app');
	}
	
	/**
	 * @var TsVar
	 */
	protected $_statVar, $_logVar;
	
	public function init() {
		\Fwe::$app = $this;
		
		parent::init();
		
		pthread_sigmask(SIG_SETMASK, []);
		pcntl_async_signals(true);
		$set = [SIGTERM, SIGINT, SIGUSR1, SIGUSR2];
		foreach($set as $sig) pcntl_signal($sig, [$this, 'signalHandler'], false);
	
		$this->_statVar = new TsVar('__stat__');
		$this->_logVar = new TsVar('__log__', 0, null, true);
		
		foreach($this->bootstrap as $id) {
			if($this->has($id)) $this->get($id);
			else $this->getModule($id);
		}
		
		ini_set('display_errors', false);
		set_exception_handler([$this, 'handleException']);
		set_error_handler([$this, 'handleError']);
	}
	
	public function __destruct() {
		\Fwe::debug(get_called_class(), $this->id, true);
	}
	
	public function handleException(\Throwable $e) {
		echo "$e\n";
		$this->log($e, static::LOG_ERROR | static::LOG_SKIP, 'handleException');
	}
	
	public function handleError(int $code, string $message, string $file, int $line) {
		if(!($code & error_reporting())) return;
		
		$level = 0;
		switch($code) {
			case E_ERROR:
			case E_CORE_ERROR:
			case E_COMPILE_ERROR:
			case E_USER_ERROR:
			case E_RECOVERABLE_ERROR:
			case E_PARSE:
				$level = static::LOG_ERROR;
				break;
			case E_WARNING:
			case E_CORE_WARNING:
			case E_COMPILE_WARNING:
			case E_USER_WARNING:
				$level = static::LOG_WARN;
				break;
			case E_STRICT:
				$level = static::LOG_INFO;
				break;
			case E_NOTICE:
			case E_USER_NOTICE:
			case E_DEPRECATED:
			case E_USER_DEPRECATED:
				$level = static::LOG_VERBOSE;
				break;
			default:
				return;
		}
		$rootDir = ROOT . '/';
		$rootLen = strlen($rootDir);
		if(!strncmp($file, $rootDir, $rootLen)) {
			$file = substr($file, $rootLen);
		}
		if($rootLen > 10) {
			$message = str_replace(ROOT . '/', '', $message);
		}
		if($level & (static::LOG_ERROR|static::LOG_WARN)) {
			$type = ($level === static::LOG_ERROR) ? 'Error' : 'Warning';
			echo "$type: $message in $file:$line\n";
		}
		$this->log("$message in $file:$line", $level | static::LOG_SKIP, 'handleError');
	}
	
	public $logLevel = self::LOG_ERROR|self::LOG_WARN|self::LOG_INFO;
	public $traceLevel = 0;
	public $logSize = 2 * 1024 * 1024;
	public $logMax = 50;
	public $logFormat = '%02d';

	protected $_logFile, $_logIdxFile, $_logFormat;
	protected $_logEvent, $_logFp;
	
	protected function logInit(bool $isEvent = true) {
		if(!is_main_task()) return;

		$name = \Fwe::$name;
		
		$this->_logFile = \Fwe::getAlias("@app/runtime/{$name}.log");
		$this->_logIdxFile = \Fwe::getAlias("@app/runtime/{$name}.idx");
		$this->_logFormat = \Fwe::getAlias("@app/runtime/{$name}-{$this->logFormat}.log");
		
		if($isEvent) {
			$this->_logEvent = $this->_logVar->newReadEvent([$this, 'logEvent']);
			$this->_logEvent->add();
		}

		$this->_logFp = fopen($this->_logFile, 'a');
		$this->_logIdx = (int) @file_get_contents($this->_logIdxFile);
	}
	
	protected function logRead(int $n) {
		if($n <= 0 || !is_main_task()) return;

		if(!$this->_logFp) $this->logInit(false);

		for($i=0; $i<$n; $i++) {
			$log = $this->_logVar->shift();
			if(!$log) continue;
			
			$time = date('Y-m-d H:i:s.', $log['time']) . sprintf('%06d', ($log['time'] * 1000000) % 1000000);
			fwrite($this->_logFp, "[$time][{$log['level']}][{$log['category']}][{$log['memory']}][{$log['taskName']}] {$log['message']}\n");
			if(isset($log['traces'])) {
				fwrite($this->_logFp, "TRACE:\n{$log['traces']}\n");
			}
		}
		
		fflush($this->_logFp);
		
		if(ftell($this->_logFp) > $this->logSize) {
			fclose($this->_logFp);
			$this->_logIdx++;
			if($this->_logIdx > $this->logMax) {
				$this->_logIdx = 1;
			}
			file_put_contents($this->_logIdxFile, $this->_logIdx);
			rename($this->_logFile, sprintf($this->_logFormat, $this->_logIdx));
			$this->_logFp = fopen($this->_logFile, 'a');
		}
	}
	
	public function logCount() {
		return $this->_logVar->count();
	}
	
	public function logEvent() {
		if(is_main_task()) {
			$this->logRead($this->_logVar->read(128));
		}
	}
	
	public function logAll() {
		if(is_main_task()) {
			$this->logRead($this->_logVar->count());
		}
	}
	
	public function log($message, int $level, string $category = 'app') {
		if(!($level & $this->logLevel)) return;
		
		$log = compact('message', 'category');
		$log['time'] = microtime(true);
		$log['memory'] = memory_get_usage();
		$log['taskName'] = THREAD_TASK_NAME;
		
		switch($level & 0xff) {
			case self::LOG_ERROR:
				$log['level'] = 'error';
				break;
			case self::LOG_WARN:
				$log['level'] = 'warning';
				break;
			case self::LOG_INFO:
				$log['level'] = 'info';
				break;
			case self::LOG_DEBUG:
				$log['level'] = 'debug';
				break;
			case self::LOG_VERBOSE:
				$log['level'] = 'verbose';
				break;
			default:
				$log['level'] = 'unknown';
				break;
		}
		
		$rootDir = ROOT . '/';
		$rootLen = strlen($rootDir);

		if($message instanceof \Throwable) {
			$log['message'] =  get_class($message) . ': ' . $message->getMessage();
			$traces = [];
			
		trace:
			foreach($message->getTrace() as $trace) {
				if(isset($trace['file'], $trace['line']) && !strncmp($trace['file'], $rootDir, $rootLen)) {
					$file = substr($trace['file'], $rootLen);
					$prefix = isset($trace['class'], $trace['type']) ? $trace['class'] . $trace['type'] : null;
					$traces[] = "  $prefix{$trace['function']} in {$file}:{$trace['line']}";
				}
			}
			if($message = $message->getPrevious()) {
				$traces = ' ' . get_class($message) . ': ' . $message->getMessage();
				goto trace;
			}
			$log['traces'] = implode("\n", $traces);
		} elseif($this->traceLevel > 0) {
			$traces = [];
			$count = 0;
			$ts = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
			if(($level & static::LOG_SKIP)) {
				array_shift($ts);
			}
			foreach ($ts as $trace) {
				if (isset($trace['file'], $trace['line']) && !strncmp($trace['file'], $rootDir, $rootLen)) {
					$file = substr($trace['file'], $rootLen);
					$prefix = isset($trace['class'], $trace['type']) ? $trace['class'] . $trace['type'] : null;
					$traces[] = "  $prefix{$trace['function']} in {$file}:{$trace['line']}";
					if (++$count >= $this->traceLevel) {
						break;
					}
				}
			}
			if($count > 0) $log['traces'] = implode("\n", $traces);
		}
		
		if(!is_scalar($log['message'])) {
			$log['message'] = json_encode($log['message'], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
		}
		
		$this->_logVar->push($log);
		$this->_logVar->write();
	}
	
	/**
	 * 打印程序中的错误信息
	 * 
	 * @param mixed $message
	 * @param string $category
	 */
	public function error($message, string $category = 'app') {
		$this->log($message, static::LOG_ERROR|static::LOG_SKIP, $category);
	}
	
	/**
	 * 打印一些警告信息，提示程序该处可能存在的风险
	 *
	 * @param mixed $message
	 * @param string $category
	 */
	public function warn($message, string $category = 'app') {
		$this->log($message, static::LOG_WARN|static::LOG_SKIP, $category);
	}
	
	/**
	 * 打印一些比较重要的数据，可帮助你分析用户行为数据
	 *
	 * @param mixed $message
	 * @param string $category
	 */
	public function info($message, string $category = 'app') {
		$this->log($message, static::LOG_INFO|static::LOG_SKIP, $category);
	}
	
	/**
	 * 打印一些调试信息
	 *
	 * @param mixed $message
	 * @param string $category
	 */
	public function debug($message, string $category = 'app') {
		$this->log($message, static::LOG_DEBUG|static::LOG_SKIP, $category);
	}
	
	/**
	 * 打印一些最为繁琐、意义不大的日志信息
	 *
	 * @param mixed $message
	 * @param string $category
	 */
	public function verbose($message, string $category = 'app') {
		$this->log($message, static::LOG_VERBOSE|static::LOG_SKIP, $category);
	}
	
	public function __isset($name) {
		if($this->has($name)) {
			return true;
		} else {
			return parent::__isset($name);
		}
	}

	public function __unset($name) {
		if($this->has($name)) {
			$this->remove($name);
		} else {
			parent::__unset($name);
		}
	}

	public function __get($name) {
		if($this->has($name)) {
			return $this->get($name);
		} else
			return parent::__get($name);
	}

	/**
	 * 获取所有组件配置或对象列表
	 * 
	 * @param bool $isObject
	 * @return array
	 */
	public function getComponents(bool $isObject = true) {
		return $this->all($isObject);
	}

	/**
	 * 设置多个组件配置或对象列表
	 * @param array $components
	 */
	public function setComponents(array $components) {
		foreach($components as $name => $compontent) {
			$this->set($name, $compontent);
		}
	}

	public function beforeAction(Action $action, array $params = []): bool {
		return true;
	}

	public function afterAction(Action $action, array $params = []) {
	}

	public $signalTimeout = 0.25;

	protected $_sigEvent;
	protected function signalEvent(?callable $call = null) {
		$this->_sigEvent = new \Event(\Fwe::$base, -1, \Event::TIMEOUT | \Event::PERSIST, function() use($call) {
			if(!$this->_running) {
				$this->signalHandler($this->_exitSig);
				// $this->_sigEvent->delTimer();
			}
			if($call) $call();
		});
		$this->_sigEvent->addTimer($this->signalTimeout);
	}
	
	protected $_running = true;
	protected $_exitSig = SIGINT;
	public function signalHandler(int $sig) {
		if(!$this->_running) {
			return;
		}

		\Fwe::$app->verbose($sig, 'signal');

		$this->_exitSig = $sig;
		$this->_running = false;
	}
	
	public function stat($key, int $inc = 1) {
		return $inc == 0 ? $this->_statVar[$key] : $this->_statVar->inc($key, $inc);
	}
	
	public function exitSig() {
		return $this->_exitSig;
	}

	public function isRunning() {
		return $this->_running;
	}
	
	public function isService() {
		return false;
	}
	
	public function isWeb() {
		return false;
	}

	abstract public function boot();
}
