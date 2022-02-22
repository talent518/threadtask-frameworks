<?php
namespace fwe\db;

abstract class AsyncConnection {
	/**
	 * @var IPool
	 */
	public $pool;
	
	/**
	 * @var array
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
			$this->_event->del();
			$this->_event = null;
			\Fwe::$app->events--;
		}
		return $this;
	}
	
	protected function trigger(\Throwable $e = null) {
		if($e === null) {
			$data = $this->_data;
			$callback = $this->_callbacks[0];
			$this->reset();
			$ret = true;
			try {
				$ret = \Fwe::invoke($callback, $data + ['db'=>$this, 'data'=>$data]) !== false;
			} catch(\Throwable $e) {
				echo "$e\n";
				goto err;
			} finally {
				$this->reset();
				if($ret) {
					$this->pool->push($this);
				} else {
					$this->pool->remove($this);
				}
			}
		} else {
			err:
			$ret = true;
			$current = $this->_current;
			try {
				$ret = \Fwe::invoke($this->_callbacks[1], ['db'=>$this, 'data'=>$this->_data, 'e'=>$e, 'event'=>$this->_current]) !== false;
			} catch(\Throwable $e) {
				echo "$e\n";
			} finally {
				if($ret) {
					$this->reset();
					$this->pool->push($this);
				} else if($current === $this->_current) {
					$this->_data[$this->_current->getKey()] = $this->_current->getData();
					$this->send();
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
				echo "$e\n";
				$this->trigger($e);
			}
		} else {
			$class = get_class($this->_current);
			$iface = IEvent::class;
			$e = new Exception("类 $class 未实现 $iface 接口");
			echo "$e\n";
			$this->trigger($e);
		}
	}
	
	public function eventCallback($fd, int $what) {
		if($what === \Event::TIMEOUT) {
			$this->pool->remove($this);
			$e = new TimeoutException("异步事件队列执行超时");
			$sql = $this->_current->getSql();
			$t = microtime(true) - $this->getTime();
			echo "SQL: $sql\n执行时间：$t\n$e\n";
			$this->trigger($e);
			$this->reset();
		} elseif($this->_current === null) {
			$this->trigger(new Exception("没有要处理的事件"));
		} else {
			try {
				$this->_current->recv();
				$this->_data[$this->_current->getKey()] = $this->_current->getData();
				$this->send();
			} catch(\Throwable $e) {
				echo "$e\n";
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
			echo new Exception("异步事件正在执行");
			return false;
		}
	}
	
	public function cancel() {
		$this->reset();
		$this->pool->remove($this);
	}
	
	abstract public function getFd();
}
