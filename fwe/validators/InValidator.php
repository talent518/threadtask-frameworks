<?php
namespace fwe\validators;

use fwe\base\Model;

class InValidator extends IValidator {
	public $range;
	public $not = false;

	public function init() {
		if($this->message === null) {
			$this->message = '{attribute} 的值无效';
		}
	}

	public function validate(Model $model, bool $isPerOne = false, bool $isOnly = false) {
		if($this->range instanceof \Closure) {
			$range = call_user_func($this->range);
		} else {
			$range = $this->range;
		}
		$ret = 0;
		foreach($this->attributes as $attr) {
			$value = $model->{$attr};
			if(($isPerOne && $model->hasError($attr)) || $this->isSkipOnEmpty($value) || $this->inArray($value, $range)) {
				continue;
			} else {
				$ret ++;
				$label = $model->getLabel($attr);
				$error = strtr($this->message, [
					'{attribute}' => $label,
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

	protected function inArray($value, array $range) {
		$ret = in_array($value, $range);

		if($this->not) {
			return !$ret;
		} else {
			return $ret;
		}
	}

}