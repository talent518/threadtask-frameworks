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
 * @method mixed get(string $id, bool $isMake = true)
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
	
		$this->events = 0;
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
	
	public function handleException(\Throwable $e) {
		if(!$this->isService()) {
			echo "$e\n";
		}
		$this->error($e, 'handleException');
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
		if(!strncmp($file, ROOT, $n = strlen(ROOT))) {
			$file = substr($file, $n + 1);
		}
		$this->log($message, $level, 'handleError');
	}
	
	public $logLevel = self::LOG_ERROR|self::LOG_WARN|self::LOG_INFO;
	public $traceLevel = 10;
	public $logSize = 2 * 1024 * 1024;
	public $logMax = 50;
	public $logFormat = '%02d';

	protected $_logFile, $_logIdxFile, $_logFormat;
	protected $_logEvent, $_logFp;
	
	protected function logInit() {
		$this->_logFile = \Fwe::getAlias('@app/runtime/app.log');
		$this->_logIdxFile = \Fwe::getAlias('@app/runtime/app.idx');
		$this->_logFormat = \Fwe::getAlias("@app/runtime/app-{$this->logFormat}.log");
		
		$this->_logEvent = $this->_logVar->newReadEvent([$this, 'logEvent']);
		$this->_logEvent->add();
		$this->_logFp = fopen($this->_logFile, 'a');
		$this->_logIdx = (int) @file_get_contents($this->_logIdxFile);
	}
	
	protected function logRead(int $n) {
		if($n <= 0) return;

		if(!$this->_logFp) $this->logInit();

		for($i=0; $i<$n; $i++) {
			$log = $this->_logVar->shift();
			$time = date('Y-m-d H:i:s.', $log['time']) . sprintf('%06d', ($log['time'] * 1000000) % 1000000);
			fwrite($this->_logFp, "[$time][{$log['level']}][{$log['category']}][{$log['memory']}][{$log['thread']}] {$log['message']}\n");
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
			file_put_contents($this->_logIdxFile, $nlog);
			rename($this->_logFile, sprintf($this->_logFormat, $nlog));
			$this->_logFp = fopen($this->_logFile, 'a');
		}
	}
	
	public function logEvent() {
		$this->logRead($this->_logVar->read(128));
	}
	
	public function logAll() {
		$this->logRead($this->_logVar->count());
	}
	
	public function log($message, int $level, string $category = 'app') {
		if(!($level & $this->logLevel)) return;
		
		switch($level) {
			case self::LOG_ERROR:
				$level = 'error';
				break;
			case self::LOG_WARN:
				$level = 'warning';
				break;
			case self::LOG_INFO:
				$level = 'info';
				break;
			case self::LOG_DEBUG:
				$level = 'debug';
				break;
			case self::LOG_VERBOSE:
				$level = 'verbose';
				break;
			default:
				$level = 'unknown';
				break;
		}
		
		$time = microtime(true);
		$memory = memory_get_usage();
		$thread = (defined('THREAD_TASK_NAME') ? THREAD_TASK_NAME : 'main');
		$log = compact('message', 'level', 'category', 'time', 'memory', 'thread');

		if($message instanceof \Throwable) {
			$log['message'] =  get_class($message) . ': ' . $message->getMessage();
			$traces = [];
			
		trace:
			foreach ($message->getTrace() as $trace) {
				if (isset($trace['file'], $trace['line']) && !strncmp($trace['file'], ROOT, $n = strlen(ROOT))) {
					$file = substr($trace['file'], $n + 1);
					$traces[] = "  {$trace['class']}{$trace['type']}{$trace['function']} in {$file}:{$trace['line']}";
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
			array_pop($ts); // remove the last trace since it would be the entry script, not very useful
			foreach ($ts as $trace) {
				if (isset($trace['file'], $trace['line']) && !strncmp($trace['file'], ROOT, $n = strlen(ROOT))) {
					$file = substr($trace['file'], $n + 1);
					$traces[] = "  {$trace['class']}{$trace['type']}{$trace['function']} in {$file}:{$trace['line']}";
					if (++$count >= $this->traceLevel) {
						break;
					}
				}
			}
			if($count > 0) $log['traces'] = implode("\n", $traces);
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
		$this->log($message, static::LOG_ERROR, $category);
	}
	
	/**
	 * 打印一些警告信息，提示程序该处可能存在的风险
	 *
	 * @param mixed $message
	 * @param string $category
	 */
	public function warn($message, string $category = 'app') {
		$this->log($message, static::LOG_WARN, $category);
	}
	
	/**
	 * 打印一些比较重要的数据，可帮助你分析用户行为数据
	 *
	 * @param mixed $message
	 * @param string $category
	 */
	public function info($message, string $category = 'app') {
		$this->log($message, static::LOG_INFO, $category);
	}
	
	/**
	 * 打印一些调试信息
	 *
	 * @param mixed $message
	 * @param string $category
	 */
	public function debug($message, string $category = 'app') {
		$this->log($message, static::LOG_DEBUG, $category);
	}
	
	/**
	 * 打印一些最为繁琐、意义不大的日志信息
	 *
	 * @param mixed $message
	 * @param string $category
	 */
	public function verbose($message, string $category = 'app') {
		$this->log($message, static::LOG_VERBOSE, $category);
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

		// $name = (defined('THREAD_TASK_NAME') ? THREAD_TASK_NAME : 'main');
		// echo "$name signal: $sig\n";

		$this->_exitSig = $sig;
		$this->_running = false;
		
		if(!defined('THREAD_TASK_NAME'))
			task_set_run(false);
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
