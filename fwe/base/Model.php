<?php
namespace fwe\base;

use fwe\traits\MethodProperty;

/**
 * 关于规则验证的模块
 * 
 * @author abao
 *
 * @property-read array $errors
 * @property-read array $safeAttributes
 * @property array $attributes
 * @property string $scene
 */
abstract class Model {
	use MethodProperty;
	
	public static function create(array $params = []) {
		return \Fwe::createObject(get_called_class(), $params);
	}
	
	public function init() {
		\Fwe::debug(get_called_class(), '', false);
	}
	
	public function __destruct() {
		\Fwe::debug(get_called_class(), '', true);
	}

	/**
	 * 错误消息
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
	 * 验证器列表
	 * 
	 * @var array|\fwe\validators\IValidator
	 */
	protected $validators = [];

	/**
	 * 清除所有错误消息
	 *
	 * @return void
	 */
	public function clearError() {
		$this->errors = [];
	}

	/**
	 * 是否有错误
	 *
	 * @return boolean
	 */
	public function hasErrors() {
		return count($this->errors);
	}

	/**
	 * 指定属性是否有错误消息
	 *
	 * @param string $attribute
	 * @return boolean
	 */
	public function hasError(string $attribute) {
		return isset($this->errors[$attribute]);
	}

	/**
	 * 删除属性的错误消息
	 *
	 * @param string $attribute
	 * @return void
	 */
	public function delError(string $attribute) {
		unset($this->errors[$attribute]);
	}

	/**
	 * 设置属性的错误消息
	 *
	 * @param string $attribute
	 * @param string $message
	 * @return void
	 */
	public function setError(string $attribute, string $message) {
		$this->errors[$attribute] = $message;
	}

	/**
	 * 添加属性的错误消息
	 *
	 * @param string $attribute
	 * @param string $message
	 * @return void
	 */
	public function addError(string $attribute, string $message) {
		$this->errors[$attribute][] = $message;
	}

	/**
	 * 获取错误消息：键为属性名，值为错误消息(验证时isPerOne参数为true时此值为字符串，否则为字符串数组)
	 *
	 * @return void
	 */
	public function getErrors() {
		return $this->errors;
	}

	/**
	 * 获取验证规则列表
	 *
	 * @return array
	 */
	abstract public function getRules();

	/**
	 * 获取标签列表：键为属性名，值为标签值
	 *
	 * @return array
	 */
	abstract protected function getLabels();

	protected $labels;

	/**
	 * 根据属性名获取其标签
	 *
	 * @param string|null $attr
	 * @return void
	 */
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

	/**
	 * 获取安全属性名列表
	 *
	 * @param boolean $isKeys
	 * @return void
	 */
	public function getSafeAttributes(bool $isKeys = true) {
		return $isKeys ? array_keys($this->safeAttributes) : $this->safeAttributes;
	}

	/**
	 * 获取所有属性：键为属性名，值为属性值
	 * 
	 * @return array
	 */
	public function getAttributes() {
		return \Fwe::getVars($this);
	}

	/**
	 * 安全的设置属性
	 *
	 * @param array $attributes
	 * @param boolean $isSafeOnly
	 * @return void
	 */
	public function setAttributes(array $attributes, bool $isSafeOnly = true) {
		if($isSafeOnly && !empty($this->safeAttributes)) {
			$attributes = array_intersect_key($attributes, $this->safeAttributes);
		}

		\Fwe::setVars($this, $attributes);
	}

	/**
	 * 获得验证器组件
	 * 
	 * @return \fwe\validators\Validator
	 */
	public function getValidator() {
		return \Fwe::$app->get('validator');
	}

	/**
	 * 获得当前场景
	 *
	 * @return string|null
	 */
	public function getScene() {
		return $this->scene;
	}

	/**
	 * 根据场景($scene)和验证规则列表生成验证器列表和安全属性名列表
	 *
	 * @param string|null $scene
	 * @return void
	 */
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
	 * 根据设置场景(scene)而构建的验证器列表进行规则验证
	 *
	 * @param callable|null $ok
	 * @param boolean $isPerOne
	 * @param boolean $isOnly
	 * @return null|int|\Closure
	 */
	public function validate(?callable $ok, bool $isPerOne = false, bool $isOnly = false, ...$args) {
		$this->errors = [];

		$ret = 0;
		$calls = [];

		/** @var \fwe\validators\IValidator $validator */
		foreach($this->validators as $validator) {
			$n = $validator->validate($this, $isPerOne, $isOnly, ...$args);
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
				$ok($ret);
			} else {
				return $ret;
			}
		} else {
			$call = (function(callable $ok) use($calls, $ret, $isOnly, $args) {
				$next = function(callable $ok) use(&$calls, $args) {
					array_shift($calls)($ok, ...$args);
				};
				$res = function(int $n, ?string $error = null) use(&$calls, &$ret, $isOnly, $next, $ok) {
					if($n < 0) {
						$ok($ret + 1, $error);
					} else {
						$ret += $n;
						if(($n && $isOnly) || empty($calls)) {
							$ok($ret);
						} else {
							$next($this);
						}
					}
				};
				$next($res->bindTo($res));
			})->bindTo(null);

			if($ok) {
				if(empty($calls)) {
					$ok($ret);
				} else {
					$call($ok);
				}
			} else {
				return $call;
			}
		}
	}

}
