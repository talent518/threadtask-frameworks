<?php
namespace fwe\validators;

use Exception;
use fwe\base\Model;

class RequiredValidator extends IValidator {

	public function validate(Model $model, bool $isPerOne = false, bool $isOnly = false) {
		$ret = 0;
		foreach($this->attributes as $attr) {
			$value = $model->{$attr};
			if($isPerOne && $model->hasError($attr)) {
				continue;
			}
			if($value === null || is_string($value) && $value === '') {
				$ret ++;
				$label = $model->getLabel($attr);
				if($isPerOne) {
					$model->setError($attr, "$label 不能为空");
				} else {
					$model->addError($attr, "$label 不能为空");
				}
				if($isOnly) {
					break;
				}
			}
		}
		return $ret;
	}

}
