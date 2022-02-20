<?php
namespace fwe\validators;

use fwe\base\Model;

class SafeValidator extends IValidator {
	public function validate(Model $model, bool $isPerOne = false, bool $isOnly = false) {
		return 0;
	}
}
