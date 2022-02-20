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
	protected $_statVar;
	
	public function init() {
		\Fwe::$app = $this;
		
		parent::init();
		
		pthread_sigmask(SIG_SETMASK, []);
		pcntl_async_signals(true);
		$set = [SIGTERM, SIGINT, SIGUSR1, SIGUSR2];
		foreach($set as $sig) pcntl_signal($sig, [$this, 'signalHandler'], false);
	
		$this->events = 0;
		$this->_statVar = new TsVar('__stat__');
		
		foreach($this->bootstrap as $id) {
			if($this->has($id)) $this->get($id);
			else $this->getModule($id);
		}
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
				$this->_sigEvent->delTimer();
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
