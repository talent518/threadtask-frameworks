<?php
namespace fwe\base;

/**
 * 可以进行组件配置并在获取时自动创建组件对象
 * 
 * @author abao
 */
class Component {

	/**
	 *
	 * @var array
	 */
	private $_defines = [];

	/**
	 *
	 * @var array
	 */
	private $_objects = [];
	
	public $params = [];

	/**
	 * 根据组件名判断组件配置或对象是否存在
	 * 
	 * @param string $name
	 * @return boolean
	 */
	public function has(string $name) {
		return isset($this->_defines[$name]) || isset($this->_objects[$name]);
	}

	/**
	 * 根据组件名获取组件对象
	 * 
	 * @param string $name
	 * @param bool $isMake
	 * @return object
	 */
	public function get(string $name, bool $isMake = true) {
		if(isset($this->_objects[$name])) {
			return $this->_objects[$name];
		} else if($isMake && isset($this->_defines[$name])) {
			return $this->_objects[$name] = \Fwe::createObject($this->_defines[$name], $this->params);
		}
	}

	/**
	 * 根据组件名设置组件配置或对象
	 * 
	 * @param string $name
	 * @param mixed $value
	 * @param bool $isFull
	 */
	public function set(string $name, $value, bool $isFull = true) {
		if(is_object($value)) {
			$this->_objects[$name] = $value;
		} elseif($isFull) {
			unset($this->_objects[$name]);
			$this->_defines[$name] = $value;
		} elseif(isset($this->_objects[$name]) && is_array($value) && (! isset($value['class']) || $value['class'] === get_class($this->_objects[$name]))) {
			$obj = $this->_objects[$name];
			foreach($value as $key => $val) {
				$obj->$key = $val;
			}
			if(isset($this->_defines[$name])) {
				goto merge;
			} else {
				if(! isset($value['class'])) {
					$value['class'] = get_class($this->_objects[$name]);
				}
				$this->_defines[$name] = $value;
			}
		} elseif(isset($this->_defines[$name])) {
			unset($this->_objects[$name]);
			merge:
			if(is_array($this->_defines[$name])) {
				if(is_array($value)) {
					if($value['class'] === $this->_defines[$name]['class']) {
						$this->_defines[$name] = array_merge($this->_defines[$name], $value);
					} else {
						$this->_defines[$name] = $value;
					}
				} else {
					$this->_defines[$name]['class'] = $value;
				}
			} else {
				if(is_array($value) && ! isset($value['class'])) {
					$value['class'] = $this->_defines[$name];
				}

				$this->_defines[$name] = $value;
			}
		} else {
			unset($this->_objects[$name]);
			$this->_defines[$name] = $value;
		}
	}

	/**
	 * 根据组件名移除组件配置和对象
	 * 
	 * @param string $name
	 */
	public function remove(string $name) {
		unset($this->_objects[$name], $this->_defines[$name]);
	}
	
	/**
	 * 获取所有组件配置或对象列表
	 * 
	 * @param bool $isObject
	 * @return array
	 */
	public function all(bool $isObject = true) {
		return $isObject ? $this->_objects : $this->_defines;
	}
}