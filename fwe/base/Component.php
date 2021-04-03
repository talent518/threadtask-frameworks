<?php
namespace fwe\base;

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

	public function has($name) {
		return isset($this->_defines[$name]) || isset($this->_objects[$name]);
	}

	public function get($name, $isMake = true) {
		if(isset($this->_objects[$name])) {
			return $this->_objects[$name];
		} else if($isMake && isset($this->_defines[$name])) {
			return $this->_objects[$name] = \Fwe::createObject($this->_defines[$name]);
		}
	}

	public function set($name, $value, bool $isFull = true) {
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

	public function remove($name) {
		unset($this->_objects[$name], $this->_defines[$name]);
	}
	
	public function all(bool $isObject = true) {
		return $isObject ? $this->_objects : $this->_defines;
	}
}