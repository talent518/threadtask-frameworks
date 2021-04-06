<?php
namespace fwe\base;

/**
 * 应用基类
 * 
 * @property-read string $runActionMethod
 * 
 * @method bool has(string $id)
 * @method mixed get(string $id, bool $isMake = true)
 * @method void set(string $id, $value, bool $isFull = true)
 * @method void remove(string $id)
 * @method array all(bool $isObject = true)
 */
abstract class Application extends Module {
	
	public $id, $name;
	
	/**
	 * @var string
	 */
	private $_runActionMethod;
	
	public function __construct(string $id, string $name, string $runActionMethod = 'runWithEvent') {
		$this->id = $id;
		$this->name = $name;
		$this->extendObject = \Fwe::createObject(Component::class);
		$this->_runActionMethod = $runActionMethod;
		
		parent::__construct($id);
	}
	
	public function getRunActionMethod() {
		return $this->_runActionMethod;
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

	abstract public function boot();
}
