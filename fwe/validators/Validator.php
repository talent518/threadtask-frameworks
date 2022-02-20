<?php
namespace fwe\validators;

use fwe\base\Component;
use fwe\base\Model;
use fwe\traits\MethodProperty;

class Validator {
	use MethodProperty;

	public $id;

	/**
	 * @var Component
	 */
	protected $component;

	public function __construct() {
		$this->component = \Fwe::createObject(Component::class);

		$this->setValidators([
			'required' => RequiredValidator::class,
			'safe' => SafeValidator::class,
		]);
	}

	public function getValidator(string $id) {
		return $this->component->get($id, false);
	}

	public function getValidators() {
		return $this->component->all(false);
	}

	public function setValidators(array $values) {
		foreach($values as $id => $value) {
			$this->component->set($id, $value);
		}
	}

	/**
	 * 构建验证器列表
	 * 
	 * @return array|IValidator
	 */
	public function build(Model $model) {
		$rets = [];
		$scene = $model->getScene();
		$class = get_class($model);

		foreach($model->getRules() as $rule) {
			if(isset($rule[0], $rule[1])) {
				$attrs = is_array($rule[0]) ? $rule[0] : preg_split('/[^0-9a-zA-Z_]+/', $rule[0], -1, PREG_SPLIT_NO_EMPTY);
				$id = $rule[1];
				unset($rule[0], $rule[1]);

				if(empty($attrs)) {
					$rule = var_export($rule, true);
					trigger_error("$class::getRules() 中的规则错误(attributes: 数组的0索引): $rule", E_USER_WARNING);
					continue;
				} else if(empty($id)) {
					$rule = var_export($rule, true);
					trigger_error("$class::getRules() 中的规则错误(id: 数组的1索引): $rule", E_USER_WARNING);
					continue;
				} else {
					$rule['attributes'] = $attrs;
				}
			} else {
				if(isset($rule[0])) {
					$id = $rule[0];
					unset($rule[0]);
				} elseif(isset($rule['id'])) {
					$id = $rule['id'];
					unset($rule['id']);
				} else {
					$id = 'required';
				}
			}

			if(empty($rule['scene'])) {
				$rets[] = \Fwe::createObject($this->getValidator($id), $rule);
			} else {
				$scenes = is_array($rule['scene']) ? $rule['scene'] : preg_split('/[^0-9a-zA-Z_]+/', $rule['scene'], -1, PREG_SPLIT_NO_EMPTY);
				if(in_array($scene, $scenes)) {
					$rets[] = \Fwe::createObject($this->getValidator($id), $rule);
				}
			}
		}

		return $rets;
	}
}