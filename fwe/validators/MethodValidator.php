<?php
namespace fwe\validators;

use fwe\base\Model;

class MethodValidator extends IValidator {
	public $method;

	public function init() {
		if(!$this->method) {
			trigger_error("method属性不能为空", E_USER_ERROR);
		}
	}

	public function validate(Model $model, bool $isPerOne = false, bool $isOnly = false) {
		$class = get_class($model);
		$attributes = $this->attributes;
		$validator = $this;
		$params = compact('validator', 'model', 'isPerOne', 'isOnly', 'attributes');
        if ($this->method instanceof \Closure) {
			return \Fwe::invoke($this->method, $params, null);
        } elseif(method_exists($model, $this->method)) {
			return \Fwe::invoke([$model, $this->method], $params, "{$class}::{$this->method}");
		} else {
			trigger_error("{$class}不存在{$this->method}方法", E_USER_ERROR);
        }
	}
}