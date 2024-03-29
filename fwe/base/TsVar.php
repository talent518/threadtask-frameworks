<?php
namespace fwe\base;

class TsVar implements \IteratorAggregate, \ArrayAccess, \Countable {

	/**
	 *
	 * @var string|int|null
	 */
	private $_key;

	/**
	 *
	 * @var resource
	 */
	private $_var;

	/**
	 *
	 * @var TsVar
	 */
	private $_parent;

	/**
	 * @var integer
	 */
	private $_expire;
	
	/**
	 * @var bool
	 */
	private $_isFd;
	
	/**
	 * @var resource
	 */
	private $_readFd;
	
	/**
	 * @var resource
	 */
	private $_writeFd;
	
	/**
	 * @var \EventBufferEvent
	 */
	private $_readEvent, $_writeEvent;
	
	/**
	 * @param string|int|null $key
	 *        	$key
	 * @param int $expire
	 * @param TsVar|null $parent
	 */
	public function __construct($key, int $expire = 0, ?TsVar $parent = null, bool $isFd = false) {
		$this->_key = $key;
		$this->_parent = $parent;
		$this->_var = ts_var_declare($key, $parent ? $parent->_var : null, $isFd);
		$this->setExpire($expire);
		$this->_isFd = $isFd;
		
		\Fwe::debug(get_called_class(), $this->_key, false);
	}
	
	public function getKey() {
		return $this->_key;
	}

	public function bindReadEvent(callable $call, int $len = 128) {
		if($len <= 0) {
			\Fwe::$app->error('read length cannot less then 1', 'ts-var');
			$len = 1;
		}
		$this->_readFd = ts_var_fd($this->_var, false);
		$this->_readEvent = new \EventBufferEvent(\Fwe::$base, $this->_readFd, 0, function() use($call, $len) {
			$data = $this->_readEvent->read($len);
			$len = strlen($data);
			$call($len, $data);
		});
		$this->_readEvent->enable(\Event::READ);
	}
	
	public function write(int $len = 1, string $data = 'a') {
		if($len <= 0) {
			\Fwe::$app->error('write length cannot less then 1', 'ts-var');
			$len = 1;
		}
		
		if($this->_writeFd === null) {
			$this->_writeFd = ts_var_fd($this->_var, true);
		}
		if($this->_writeEvent === null) {
			$this->_writeEvent = new \EventBufferEvent(\Fwe::$base, $this->_writeFd, 0);
		}
		
		return $this->_writeEvent->write($len > 1 ? str_repeat($data, $len) : $data);
	}

	public function getParent() {
		return $this->_parent;
	}

	public function getVar() {
		return $this->_var;
	}

	public function getExpire() {
		return $this->_expire;
	}

	public function setExpire(int $expire) {
		if($expire > 0) {
			$this->_expire = $expire;
			ts_var_expire($this->_var, $expire + time());
		} else {
			$this->_expire = 0;
			ts_var_expire($this->_var, 0);
		}
	}

	#[\ReturnTypeWillChange]
	public function getIterator() {
		return new \ArrayIterator(ts_var_get($this->_var));
	}

	#[\ReturnTypeWillChange]
	public function offsetExists($offset) {
		return ts_var_exists($this->_var, $offset);
	}

	#[\ReturnTypeWillChange]
	public function offsetGet($offset) {
		return ts_var_get($this->_var, $offset);
	}

	#[\ReturnTypeWillChange]
	public function offsetSet($offset, $value) {
		if($offset === null)
			return ts_var_push($this->_var, $value);
		else
			return ts_var_set($this->_var, $offset, $value);
	}

	#[\ReturnTypeWillChange]
	public function offsetUnset($offset) {
		return ts_var_del($this->_var, $offset);
	}

	#[\ReturnTypeWillChange]
	public function count() {
		return ts_var_count($this->_var);
	}

	public function getCount() {
		return ts_var_count($this->_var);
	}

	public function __get($name) {
		return ts_var_get($this->_var, $name);
	}

	public function __set($name, $value) {
		ts_var_set($this->_var, $name, $value);
	}

	public function __isset($name) {
		return ts_var_exists($this->_var, $name);
	}

	public function __unset($name) {
		ts_var_del($this->_var, $name);
	}

	public function __call($name, $params) {
		if(! strncmp($name, 'set', 3)) {
			$name = lcfirst(substr($name, 3));
			ts_var_set($this->_var, $name, array_shift($params));
			return $this;
		} else {
			throw new Exception('正在调用未知方法：' . get_class($this) . "::$name()");
		}
	}

	public function clean(int $expire = 0) {
		return ts_var_clean($this->_var, $expire);
	}

	public function all(bool $isDel = false) {
		return ts_var_get($this->_var, null, $isDel);
	}

	public function has($key) {
		return ts_var_exists($this->_var, $key);
	}

	public function get($key, $def = null) {
		return ts_var_exists($this->_var, $key) ? ts_var_get($this->_var, $key) : $def;
	}
	
	public function getOrSet($key, callable $callback, int $expire = 0, ...$params) {
		return ts_var_get_or_set($this->_var, $key, $callback, $expire, ...$params);
	}
	
	public function lock($key, int $expire, bool $isWrite = false) {
		return ts_var_lock($this->_var, $key, $expire, $isWrite);
	}
	
	public function unlock($key, bool $isWrite = false) {
		return ts_var_unlock($this->_var, $key, $isWrite);
	}

	public function set($key, $value, int $expire = 0) {
		return ts_var_set($this->_var, $key, $value, $expire);
	}

	public function remove($key) {
		return ts_var_get($this->_var, $key, true);
	}

	public function push(...$params) {
		return ts_var_push($this->_var, ...$params);
	}

	public function shift(bool $isRetKey = false, &$key = null) {
		return $isRetKey ? ts_var_shift($this->_var, $key) : ts_var_shift($this->_var);
	}

	public function pop(bool $isRetKey = false, &$key = null) {
		return $isRetKey ? ts_var_pop($this->_var, $key) : ts_var_pop($this->_var);
	}

	public function minmax(&$key, bool $isMax = false, bool $isKey = false) {
		return ts_var_minmax($this->_var, $isMax, $isKey, $key);
	}
	
	public function inc($key, $inc = 1) {
		return ts_var_inc($this->_var, $key, $inc);
	}
	
	/**
	 * @return boolean|array
	 */
	public function keys() {
		return ts_var_keys($this->_var);
	}
	
	/**
	 * @return boolean|array
	 */
	public function expires() {
		return ts_var_keys($this->_var);
	}

	/**
	 * @var bool
	 */
	public $isAutoRemove = false;

	public function __destruct() {
		if($this->_readFd) {
			socket_export_fd($this->_readFd, true);
			$this->_readFd = null;
		}
		
		if($this->_writeFd) {
			socket_export_fd($this->_writeFd, true);
			$this->_writeFd = null;
		}
		
		if($this->isAutoRemove) {
			if($this->_parent) {
				ts_var_del($this->_parent->_var, $this->_key);
			} else {
				ts_var_del(ts_var_declare(null), $this->_key);
			}
		}
		
		$this->_var = null;
		$this->_parent = null;
		
		\Fwe::debug(get_called_class(), $this->_key, true);
	}
}
