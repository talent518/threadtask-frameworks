<?php
namespace fwe\base;

use fwe\traits\MethodProperty;

/**
 * @author abao
 * @property-read array $errors
 * @property-read array $safeAttributes
 * @property array $attributes
 * @property string $scene
 */
abstract class Model {
	use MethodProperty;

	/**
	 * 错误信息
	 * 
	 * @var array
	 */
	protected $errors = [];

	/**
	 * 验证器应用场景
	 * 
	 * @var string
	 */
	protected $scene;

	/**
	 * 安全属性名
	 * 
	 * @var array|string
	 */
	protected $safeAttributes = [];

	/**
	 * @var array|\fwe\validators\IValidator
	 */
	protected $validators = [];

	public function clearError() {
		$this->errors = [];
	}

	public function isError() {
		return count($this->errors);
	}

	public function hasError(string $attribute) {
		return isset($this->errors[$attribute]);
	}

	public function delError(string $attribute) {
		unset($this->errors[$attribute]);
	}

	public function setError(string $attribute, string $message) {
		$this->errors[$attribute] = $message;
	}

	public function addError(string $attribute, string $message) {
		$this->errors[$attribute][] = $message;
	}

	public function getErrors() {
		return $this->errors;
	}

	abstract public function getRules();

	abstract protected function getLabels();

	protected $labels;
	public function getLabel(?string $attr) {
		if($this->labels === null) {
			$this->labels = $this->getLabels();
		}
		if($attr === null || $attr === '') {
			return $this->labels;
		} else {
			return $this->labels[$attr] ?? $attr;
		}
	}

	public function getSafeAttributes(bool $isKey = true) {
		return $isKey ? array_keys($this->safeAttributes) : $this->safeAttributes;
	}

	/**
	 * @return array
	 */
	public function getAttributes() {
		return \Fwe::getVars($this);
	}

	public function setAttributes(array $attributes, bool $isSafeOnly = true) {
		if(!$isSafeOnly || empty($this->safeAttributes)) {
			foreach($attributes as $name => $value) {
				$this->{$name} = $value;
			}
		} else {
			foreach($attributes as $name => $value) {
				if(isset($this->safeAttributes[$name])) {
					$this->{$name} = $value;
				}
			}
		}
	}

	/**
	 * @return \fwe\validators\Validator
	 */
	public function getValidator() {
		return \Fwe::$app->get('validator');
	}

	public function getScene() {
		return $this->scene;
	}

	public function setScene(?string $scene) {
		if($scene !== $this->scene) {
			$this->scene = $scene;
			if($scene === null) {
				$this->safeAttributes = [];
				$this->validators = [];
			} else {
				$this->validators = $this->getValidator()->build($this);
				$this->safeAttributes = [];
				/** @var \fwe\validators\IValidator $validator */
				foreach($this->validators as $validator) {
					foreach($validator->attributes as $attr) {
						if(isset($this->safeAttributes[$attr])) {
							$this->safeAttributes[$attr] ++;
						} else {
							$this->safeAttributes[$attr] = 1;
						}
					}
				}
			}
		}
	}

	/**
	 * @var callable|int
	 */
	public function validate(callable $ok, bool $isPerOne = false, bool $isOnly = false) {
		$this->errors = [];

		$ret = 0;
		$calls = [];

		/** @var \fwe\validators\IValidator $validator */
		foreach($this->validators as $validator) {
			$n = $validator->validate($this, $isPerOne, $isOnly);
			if($n instanceof \Closure) {
				$calls[] = $n;
			} else {
				$ret += $n;
				if($isOnly && $n) {
					break;
				}
			}
		}

		if(empty($calls) || ($ret && $isOnly)) {
			if($ok) {
				call_user_func($ok, $ret);
			} else {
				return $ret;
			}
		} else {
			$call = (function(callable $ok) use($calls, $ret, $isOnly) {
				$next = function(callable $ok) use(&$calls) {
					$call = array_shift($calls);
					if($call) {
						$call($ok);
					} else {
						call_user_func($ok, 0);
					}
				};
				$res = function(int $n) use(&$calls, &$ret, $isOnly, $next, $ok) {
					$ret += $n;
					if(($n && $isOnly) || empty($calls)) {
						call_user_func($ok, $ret);
					} else {
						$next($this);
					}
				};
				$next($res->bindTo($res));
			})->bindTo(null);

			if($ok) {
				$call($ok);
			} else {
				return $call;
			}
		}
	}
}
