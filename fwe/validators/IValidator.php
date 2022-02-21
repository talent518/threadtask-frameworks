<?php
namespace fwe\validators;

use fwe\base\Model;

abstract class IValidator {
    /**
     * @var array
     */
    public $attributes = [];
    public $skipOnEmpty = true;
	public $message;

    abstract public function validate(Model $model, bool $isPerOne = false, bool $isOnly = false);

	protected function isSkipOnEmpty($value) {
		return $this->skipOnEmpty && $this->isEmpty($value);
	}

	protected function isEmpty($value) {
		return $value === null || (is_string($value) && $value === '');
	}
}
