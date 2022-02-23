<?php
namespace fwe\validators;

use fwe\base\Model;

class MatchValidator extends IValidator {
	public $pattern;
	public $min;
	public $tooSmall;
	public $max;
	public $tooBig;
	public $isNumeric = false;

	public function init() {
		if(!$this->pattern) {
			$class = get_class($this);
			trigger_error("{$class}的pattern属性不能为空", E_USER_ERROR);
		}

		if($this->message === null) {
			$this->message = '{attribute} 的值无效';
		}

		if($this->isNumeric) {
			if($this->tooSmall === null) {
				$this->tooSmall = '{attribute} 的值不能小于 {min}';
			}

			if($this->tooBig === null) {
				$this->tooBig = '{attribute} 的值不能大于 {max}';
			}
		} else {
			if($this->tooSmall === null) {
				$this->tooSmall = '{attribute} 长度不能小于 {min}';
			}

			if($this->tooBig === null) {
				$this->tooBig = '{attribute} 长度不能大于 {max}';
			}
		}
	}

	public function validate(Model $model, bool $isPerOne = false, bool $isOnly = false) {
		$ret = 0;
		foreach($this->attributes as $attr) {
			$value = $model->{$attr};
			if(($isPerOne && $model->hasError($attr)) || $this->isSkipOnEmpty($value) || $this->isValid($value)) {
				continue;
			} else {
				$ret ++;
				$label = $model->getLabel($attr);
				$error = strtr($this->_message, [
					'{attribute}' => $label,
					'{min}' => $this->min,
					'{max}' => $this->max,
				]);
				if($isPerOne) {
					$model->setError($attr, $error);
				} else {
					$model->addError($attr, $error);
				}
				if($isOnly) {
					break;
				}
			}
		}
		return $ret;
	}

	protected $_message;
	protected function isValid($value) {
		if(!preg_match($this->pattern, $value)) {
			$this->_message = $this->message;
			return false;
		}

		if($this->isNumeric) {
			$value = (float) $value;
		} else {
			$value = strlen((string) $value);
		}

		if($this->min !== null && $value < $this->min) {
			$this->_message = $this->tooSmall;
			return false;
		}

		if($this->max !== null && $value > $this->max) {
			$this->_message = $this->tooBig;
			return false;
		}

		return true;
	}

}