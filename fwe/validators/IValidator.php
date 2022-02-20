<?php
namespace fwe\validators;

use fwe\base\Model;

abstract class IValidator {
    /**
     * @var array
     */
    public $attributes = [];
    public $required = false;

    abstract public function validate(Model $model, bool $isPerOne = false, bool $isOnly = false);
}
