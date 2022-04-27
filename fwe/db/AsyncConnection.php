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
	 * @var int
	 */
	public $eventKey = 0;
	
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
		$key = $this->_current->getKey();
		if(!$new || !isset($this->_data[$key])) {
			$this->_data[$key] = ($new ? $data : $this->_current->getData());
		}
	}
	
	protected function trigger(\Throwable $e = null) {
		if($e === null) {
			try {
				\Fwe::invoke($this->_callbacks[0], $this->_data + ['db'=>$this, 'data'=>$this->_data]) !== false;
			} catch(\Throwable $e) {
				$this->trigger($e);
			} finally {
				$this->reset();
			}
		} else {
			try {
				$this->_current->error($e);
				$this->setData();
				$this->send();
			} catch(\Throwable $e) {
				 $this->setData(true, (string) $e);

				try {
					\Fwe::invoke($this->_callbacks[1], ['db'=>$this, 'data'=>$this->_data, 'e'=>$e, 'err'=>$e, 'error'=>$e, 'event'=>$this->_current]) !== false;
				} finally {
					$this->reset();
				}
			}
		}
	}
	
	protected function send() {
		$this->_current = array_shift($this->_events);
		if($this->_current === null) {
			$this->trigger();
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
			try {
				$this->_current->recv();
				$this->setData();
				$this->send();
			} catch(\Throwable $e) {
				$this->trigger($e);
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
		$this->pool->push($this);
	}

	public function remove() {
		$this->reset();
		$this->pool->remove($this);
		$this->pool = null;
	}
	
	abstract public function open();
	abstract public function ping(): bool;
	abstract public function close();
	abstract public function isClosed(): bool;
	abstract protected function getFd();
}
