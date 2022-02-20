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
	
	/**
	 * 根据组件名判断组件配置或对象是否存在
	 * 
	 * @param string $id
	 * @return boolean
	 */
	public function has(string $id, bool $isObject = false) {
		if($isObject) {
			return isset($this->_objects[$id]);
		} else {
			return isset($this->_defines[$id]);
		}
	}

	/**
	 * 根据组件名获取组件对象
	 * 
	 * @param string $id
	 * @param bool $isMake
	 * @param array $params
	 * @return object
	 */
	public function get(string $id, bool $isMake = true, array $params = []) {
		if(isset($this->_defines[$id])) {
			if($isMake) {
				if(isset($this->_objects[$id])) {
					return $this->_objects[$id];
				} else {
					return $this->_objects[$id] = \Fwe::createObject($this->_defines[$id], ['id'=>$id] + $params);
				}
			} else {
				return $this->_defines[$id];
			}
		} elseif(isset($this->_objects[$id])) {
			return $this->_objects[$id];
		}
	}

	/**
	 * 根据组件名设置组件配置或对象
	 * 
	 * @param string $id
	 * @param mixed $value
	 * @param bool $isFull
	 */
	public function set(string $id, $value, bool $isFull = false) {
		if(is_object($value)) {
			$this->_objects[$id] = $value;
		} elseif($isFull) {
			unset($this->_objects[$id]);
			$this->_defines[$id] = $value;
		} elseif(isset($this->_objects[$id]) && is_array($value) && (! isset($value['class']) || $value['class'] === get_class($this->_objects[$id]))) {
			$obj = $this->_objects[$id];
			foreach($value as $key => $val) {
				$obj->$key = $val;
			}
			if(isset($this->_defines[$id])) {
				goto merge;
			} else {
				if(! isset($value['class'])) {
					$value['class'] = get_class($this->_objects[$id]);
				}
				$this->_defines[$id] = $value;
			}
		} elseif(isset($this->_defines[$id])) {
			unset($this->_objects[$id]);
			merge:
			if(is_array($this->_defines[$id])) {
				if(is_array($value)) {
					if($value['class'] === $this->_defines[$id]['class']) {
						$this->_defines[$id] = array_merge($this->_defines[$id], $value);
					} else {
						$this->_defines[$id] = $value;
					}
				} else {
					$this->_defines[$id]['class'] = $value;
				}
			} else {
				if(is_array($value) && ! isset($value['class'])) {
					$value['class'] = $this->_defines[$id];
				}

				$this->_defines[$id] = $value;
			}
		} else {
			unset($this->_objects[$id]);
			$this->_defines[$id] = $value;
		}
	}

	/**
	 * 根据组件名移除组件配置和对象
	 * 
	 * @param string $id
	 */
	public function remove(string $id) {
		unset($this->_objects[$id], $this->_defines[$id]);
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