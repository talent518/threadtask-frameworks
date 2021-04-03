<?php
namespace fwe\base;

use fwe\traits\MethodProperty;

abstract class Application extends Component {
	use MethodProperty {
		__isset as traitIsset;
		__unset as traitUnset;
		__get as traitGet;
	}

	/**
	 *
	 * @var Component
	 */
	private $_modules;

	public function __construct() {
		$this->_modules = \Fwe::createObject(Component::class);
	}

	public function __isset($name) {
		if($this->has($name)) {
			return true;
		} else {
			return $this->traitIsset($name);
		}
	}

	public function __unset($name) {
		if($this->has($name)) {
			$this->remove($name);
		} else {
			$this->traitUnset($name);
		}
	}

	public function __get($name) {
		if($this->has($name)) {
			return $this->get($name);
		} else
			return $this->traitGet($name);
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

	public function hasModule($name) {
		return $this->_modules->has($name);
	}

	public function getModule($name) {
		return $this->_modules->get($name);
	}

	public function setModule($name, $value, bool $isFull) {
		$this->_modules->set($name, $value, $isFull);
	}

	/**
	 * 获取所有模块配置或对象列表
	 *
	 * @param bool $isObject
	 * @return array
	 */
	public function getModules(bool $isObject = true) {
		return $this->_modules->all($isObject);
	}

	public function setModules(array $modules) {
		foreach($modules as $name => $value) {
			$this->_modules->set($name, $value);
		}
	}

	abstract public function boot();
}
