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

	private $_expire = 0;

	/**
	 *
	 * @param string|int|null $key
	 *        	$key
	 * @param int $expire
	 * @param TsVar|null $parent
	 */
	public function __construct($key, int $expire = 0, ?TsVar $parent = null) {
		$this->_key = $key;
		$this->_parent = $parent;
		$this->_var = ts_var_declare($key, $parent ? $parent->_var : null);
		$this->setExpire($expire);
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

	public function getIterator() {
		return new \ArrayIterator(ts_var_get($this->_var));
	}

	public function offsetExists($offset) {
		return ts_var_exists($this->_var, $offset);
	}

	public function offsetGet($offset) {
		return ts_var_get($this->_var, $offset);
	}

	public function offsetSet($offset, $value) {
		if($offset === null)
			return ts_var_push($this->_var, $value);
		else
			return ts_var_set($this->_var, $offset, $value);
	}

	public function offsetUnset($offset) {
		return ts_var_del($this->_var, $offset);
	}

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

	public function set($key, $value) {
		return ts_var_set($this->_var, $key, $value);
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

	public $isAutoRemove = false;

	public function __destruct() {
		if(! $this->isAutoRemove)
			return;

		if($this->_parent) {
			return ts_var_del($this->_parent->_var, $this->_key);
		} else {
			return ts_var_del(ts_var_declare(null), $this->_key);
		}
	}
}
