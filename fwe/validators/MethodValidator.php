<?php
namespace fwe\validators;

use fwe\base\Model;

class MethodValidator extends IValidator {
	public $method;

	public function init() {
		if(!$this->method) {
			$class = get_class($this);
			trigger_error("{$class}的method属性不能为空", E_USER_ERROR);
		}
	}

	public function validate(Model $model, bool $isPerOne = false, bool $isOnly = false) {
		$ret = 0;
		foreach($this->attributes as $attr) {
			$value = $model->{$attr};
			if(($isPerOne && $model->hasError($attr)) || $this->isSkipOnEmpty($value)) {
				continue;
			} else {
				$ret ++;
			}
		}
		if($ret) {
			$class = get_class($model);
			$attributes = $this->attributes;
			$params = compact('model', 'isPerOne', 'isOnly', 'attributes');
			if ($this->method instanceof \Closure) {
				return \Fwe::invoke($this->method, $params, null);
			} elseif(method_exists($model, $this->method)) {
				return \Fwe::invoke([$model, $this->method], $params, "{$class}::{$this->method}");
			} else {
				trigger_error("{$class}不存在{$this->method}方法", E_USER_ERROR);
			}
		} else {
			return 0;
		}
	}
}