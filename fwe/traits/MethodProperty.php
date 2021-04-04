<?php
namespace fwe\traits;

use fwe\base\Exception;

/**
 * 使用魔术方法实现属性的getter/setter方法，如下：
 * - function getName(): string{}
 * - function setName($value): void{}
 * 
 * @author abao
 */
trait MethodProperty {

	protected $extendObject;

	/**
	 * 读取不存在的属性时自动调用的方法
	 * 
	 * @param string $name
	 * @throws Exception
	 * @return mixed
	 */
	public function __get(string $name) {
		$getter = 'get' . $name;
		if(method_exists($this, $getter)) {
			return $this->$getter();
		} elseif(method_exists($this, 'set' . $name)) {
			throw new Exception('正在读取只写属性：' . get_class($this) . '::' . $name);
		} elseif(is_object(($this->extendObject))) {
			return $this->extendObject->$name;
		} else {
			throw new Exception('正在读取未知属性：' . get_class($this) . '::' . $name);
		}
	}

	/**
	 * 给不存在的属性赋值时自动调用的方法
	 * 
	 * @param string $name
	 * @param mixed $value
	 * @throws Exception
	 */
	public function __set(string $name, $value) {
		$setter = 'set' . $name;
		if(method_exists($this, $setter)) {
			$this->$setter($value);
		} elseif(method_exists($this, 'get' . $name)) {
			throw new Exception('正在设置只读属性：' . get_class($this) . '::' . $name);
		} elseif(is_object(($this->extendObject))) {
			$this->extendObject->$name = $value;
		} else {
			throw new Exception('正在设置未知属性：' . get_class($this) . '::' . $name);
		}
	}

	/**
	 * 被isset的属性不存在时自动调用的方法
	 *
	 * @param string $name
	 * @return boolean
	 */
	public function __isset(string $name) {
		$getter = 'get' . $name;
		if(method_exists($this, $getter)) {
			return $this->$getter() !== null;
		} elseif(is_object(($this->extendObject))) {
			return isset($this->extendObject->$name);
		}
		return false;
	}

	/**
	 * 被unset的属性不存在时自动调用的方法
	 *
	 * @param string $name
	 * @throws Exception
	 */
	public function __unset(string $name) {
		$setter = 'set' . $name;
		if(method_exists($this, $setter)) {
			$this->$setter(null);
		} elseif(method_exists($this, 'get' . $name)) {
			throw new Exception('正在取消只读属性：' . get_class($this) . '::' . $name);
		} elseif(is_object(($this->extendObject))) {
			unset($this->extendObject->$name);
		}
	}

	/**
	 * 被调用方法不存在时被自动调用的方法
	 *
	 * @param string $name
	 * @param array $params
	 * @throws Exception
	 * @return \fwe\traits\MethodProperty|mixed
	 */
	public function __call(string $name, array $params) {
		if(! strncmp($name, 'set', 3)) {
			$name = lcfirst(substr($name, 3));
			$this->$name = array_shift($params);
			return $this;
		} elseif(is_object(($this->extendObject))) {
			return $this->extendObject->$name(...$params);
		} else {
			throw new Exception('正在调用未知方法：' . get_class($this) . "::$name()");
		}
	}
}