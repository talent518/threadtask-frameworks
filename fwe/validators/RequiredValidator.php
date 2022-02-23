<?php
namespace fwe\validators;

use Exception;
use fwe\base\Model;

class RequiredValidator extends IValidator {

	public function init() {
		if($this->message === null) {
			$this->message = '{attribute} 不能为空';
		}
	}

	public function validate(Model $model, bool $isPerOne = false, bool $isOnly = false) {
		$ret = 0;
		foreach($this->attributes as $attr) {
			$value = $model->{$attr};
			if($isPerOne && $model->hasError($attr)) {
				continue;
			} elseif($this->isEmpty($value)) {
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

}
