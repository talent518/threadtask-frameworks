<?php
namespace fwe\traits;

use fwe\base\Exception;

trait MethodProperty {
	
	protected $extendObject;

	public function __get($name) {
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

	public function __set($name, $value) {
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

	public function __isset($name) {
		$getter = 'get' . $name;
		if(method_exists($this, $getter)) {
			return $this->$getter() !== null;
		} elseif(is_object(($this->extendObject))) {
			return isset($this->extendObject->$name);
		}
		return false;
	}

	public function __unset($name) {
		$setter = 'set' . $name;
		if(method_exists($this, $setter)) {
			$this->$setter(null);
		} elseif(method_exists($this, 'get' . $name)) {
			throw new Exception('正在取消只读属性：' . get_class($this) . '::' . $name);
		} elseif(is_object(($this->extendObject))) {
			unset($this->extendObject->$name);
		}
	}

	public function __call($name, $params) {
		if(!strncmp($name, 'set', 3)) {
			$name = lcfirst(substr($name, 3));
			$this->$name = array_shift($params);
			return $this;
		} elseif(is_object(($this->extendObject))) {
			return $this->extendObject->$name($params);
		} else {
			throw new Exception('正在调用未知方法：' . get_class($this) . "::$name()");
		}
	}
}