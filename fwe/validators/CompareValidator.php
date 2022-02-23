<?php
namespace fwe\validators;

use fwe\base\Model;

class CompareValidator extends IValidator {
	public $compareAttribute;
	public $compareValue;
	public $operator = '===';
	public $isNumeric = false;

	public function init() {
		if($this->message === null) {
			switch ($this->operator) {
				case '==':
				case '===':
					$this->message = '{attribute} 的值必须等于 {compare}';
					break;
				case '!=':
				case '!==':
					$this->message = '{attribute} 的值不能等于 {compare}';
					break;
				case '>':
					$this->message = '{attribute} 的值必须大于 {compare}';
					break;
				case '>=':
					$this->message = '{attribute} 的值必须大于或等于 {compare}';
					break;
				case '<':
					$this->message = '{attribute} 的值必须小于 {compare}';
					break;
				case '<=':
					$this->message = '{attribute} 的值必须小于或等于 {compare}';
					break;
				default:
					$this->message = "{attribute} {$this->operator} {compare}";
					break;
			}
		}
	}

	public function validate(Model $model, bool $isPerOne = false, bool $isOnly = false) {
		if($this->compareAttribute) {
			$compareValue = $model->{$this->compareAttribute};
			$compare = $model->getLabel($this->compareAttribute) . ' 的值';
		} elseif($this->compareValue instanceof \Closure) {
			$compare = $compareValue = call_user_func($this->compareValue);
		} else {
			$compare = $compareValue = $this->compareValue;
		}
		if($this->isSkipOnEmpty($compareValue)) {
			return 0;
		}
		$ret = 0;
		foreach($this->attributes as $attr) {
			$value = $model->{$attr};
			if(($isPerOne && $model->hasError($attr)) || $this->isSkipOnEmpty($value) || $this->compareValues($value, $compareValue)) {
				continue;
			} else {
				$ret ++;
				$label = $model->getLabel($attr);
				$error = strtr($this->message, [
					'{attribute}' => $label,
					'{compare}' => $compare,
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

	protected function compareValues($value, $compareValue) {
		if ($this->isNumeric) {
			$value = (float) $value;
			$compareValue = (float) $compareValue;
		} else {
			$value = (string) $value;
			$compareValue = (string) $compareValue;
		}
		switch ($this->operator) {
			case '==':
				return $value == $compareValue;
			case '===':
				return $value === $compareValue;
			case '!=':
				return $value != $compareValue;
			case '!==':
				return $value !== $compareValue;
			case '>':
				return $value > $compareValue;
			case '>=':
				return $value >= $compareValue;
			case '<':
				return $value < $compareValue;
			case '<=':
				return $value <= $compareValue;
			default:
				return false;
		}
	}

}