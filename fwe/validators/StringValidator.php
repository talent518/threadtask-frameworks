<?php
namespace fwe\validators;

use fwe\base\Model;

class StringValidator extends IValidator {
	public $min;
	public $tooSmall;
	public $max;
	public $tooBig;
	public $len;
	public $notLen;
	public $charset;
	
	public function init() {
		if($this->message === null) {
			$this->message = '{attribute} 的值无效';
		}
		
		if($this->tooSmall === null) {
			$this->tooSmall = '{attribute} 长度不能小于 {min}';
		}
		
		if($this->tooBig === null) {
			$this->tooBig = '{attribute} 长度不能大于 {max}';
		}
		
		if($this->notLen === null) {
			$this->notLen = '{attribute} 长度不等于 {len}';
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
					'{len}' => $this->len,
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
		if($this->charset) {
			if(function_exists('mb_strlen')) {
				$value = mb_strlen((string) $value, $this->charset);
			} elseif(function_exists('iconv_strlen')) {
				$value = iconv_strlen((string) $value, $this->charset);
			} else {
				$value = strlen((string) $value);
			}
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
		
		if($this->len !== null && $value === $this->len) {
			$this->_message = $this->notLen;
			return false;
		}
		
		return true;
	}
	
}