<?php
namespace fwe\db;

abstract class AsyncConnection {
	/**
	 * @var IPool
	 */
	protected $_pool;
	
	/**
	 * @var array $_events
	 * @var array $_data
	 * @var array $_callbacks
	 */
	protected $_events = [], $_data = [], $_callbacks = [];
	
	/**
	 * @var IEvent
	 */
	protected $_current;
	
	/**
	 * @var \Event
	 */
	protected $_event;
	
	/**
	 * @var float
	 */
	protected $_time;
	
	/**
	 * @var int
	 */
	public $eventKey = 0;

	/**
	 * @see MySQLPool::push()
	 * @see MySQLPool::pop()
	 *
	 * @var int
	 */
	public $iUsed;
	
	public function reset() {
		$this->eventKey = 0;
		$this->_events = [];
		$this->_data = [];
		$this->_callbacks = [];
		$this->_current = null;
		if($this->_event) {
			$this->_event->free();
			$this->_event = null;
			\Fwe::$app->events--;
		}
		return $this;
	}
	
	protected function setData(bool $new = false, $data = null) {
		if($this->_current) {
			$key = $this->_current->getKey();
			if(!$new || !isset($this->_data[$key])) {
				$this->_data[$key] = ($new ? $data : $this->_current->getData());
			}
		}
	}
	
	protected function trigger(\Throwable $e) {
		try {
			$this->_current->error($e);
			$this->setData();
			$this->send();
		} catch(\Throwable $e) {
			$this->setData(true, $e->getMessage());
			$call = $this->_callbacks[1];
			$params = ['db'=>$this, 'data'=>$this->_data, 'e'=>$e, 'err'=>$e, 'error'=>$e, 'event'=>$this->_current];
			$this->reset();
			try {
				\Fwe::invoke($call, $params);
			} catch(\Throwable $e) {
				\Fwe::$app->error($e, 'async-conn');
			}
		}
	}
	
	protected function send() {
		$this->_time = microtime(true);
		$this->_current = array_shift($this->_events);
		if($this->_current === null) { // 事件队列处理完成
			$call = $this->_callbacks[0];
			$params = $this->_data + ['db'=>$this, 'data'=>$this->_data];
			$this->reset();
			try {
				\Fwe::invoke($call, $params);
			} catch(\Throwable $e) {
				\Fwe::$app->error($e, 'async-conn');
			}
		} elseif($this->_current instanceof IEvent) {
			try {
				$this->_current->send();
			} catch(\Throwable $e) {
				$this->trigger($e);
			}
		} else {
			$class = get_class($this->_current);
			$iface = IEvent::class;
			$this->trigger(new Exception("类 $class 未实现 $iface 接口"));
		}
	}
	
	public function eventCallback($fd, int $what) {
		if($what === \Event::TIMEOUT) {
			$sql = $this->_current->getSql();
			$this->trigger(new TimeoutException("异步事件队列执行超时: $sql", $t));
			$this->reset();
			$this->remove();
		} elseif($this->_current === null) {
			$this->trigger(new Exception("没有要处理的事件"));
		} else {
			$this->_time = microtime(true);
			try {
				$this->_current->recv();
			} catch(\Throwable $e) {
				$this->trigger($e);
			} finally {
				$this->setData();
				$this->send();
			}
		}
	}

	public function goAsync(callable $success, callable $error, float $timeout = -1) {
		if($this->_event === null) {
			if($this->_events) {
				$this->_event = new \Event(\Fwe::$base, $this->getFd(), \Event::READ | \Event::PERSIST, [$this, 'eventCallback']);
				$this->_event->add($timeout);
				\Fwe::$app->events++;
				$this->_callbacks = [$success, $error];
				$this->send();
				return true;
			} else {
				echo new Exception("异步事件队列为空");
				return false;
			}
		} else {
			return true;
		}
	}
	
	public function push() {
		$this->reset();
		$this->_pool->push($this);
	}

	public function remove() {
		$this->reset();
		$this->_pool->remove($this);
		$this->_pool = null;
	}
	
	public function getTime() {
		return $this->_time;
	}
	
	public function isUsing(): bool {
		return $this->_event && $this->_events;
	}
	
	public function init() {
		\Fwe::debug(get_called_class(), '', false);
	}
	
	public function __destruct() {
		$this->reset();
		\Fwe::debug(get_called_class(), '', true);
	}
	
	abstract public function open();
	abstract public function ping(): bool;
	abstract public function close();
	abstract public function isClosed(): bool;
	abstract protected function getFd();
}
