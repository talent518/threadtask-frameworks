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
			'compare' => CompareValidator::class,
			'boolean' => [
				'class' => InValidator::class,
				'range' => ['1', '0'],
			],
			'in' => InValidator::class,
			'match' => MatchValidator::class,
			'double' => [
				'class' => MatchValidator::class,
				'pattern' => '/^[-+]?[0-9]*\.?[0-9]+([eE][-+]?[0-9]+)?$/',
				'isNumeric' => true,
			],
			'integer' => [
				'class' => MatchValidator::class,
				'pattern' => '/^[+-]?\d+$/',
				'isNumeric' => true,
			],
			'url' => [
				'class' => MatchValidator::class,
				'pattern' => '/^https?:\/\/(([A-Z0-9][A-Z0-9_-]*)(\.[A-Z0-9][A-Z0-9_-]*)+)(?::\d{1,5})?(?:$|[?\/#])/i',
			],
			'ipv4' => [
				'class' => MatchValidator::class,
				'pattern' => '/^(?:(?:2(?:[0-4][0-9]|5[0-5])|[0-1]?[0-9]?[0-9])\.){3}(?:(?:2([0-4][0-9]|5[0-5])|[0-1]?[0-9]?[0-9]))$/',
			],
			'ipv6' => [
				'class' => MatchValidator::class,
				'pattern' => '/^(([0-9a-fA-F]{1,4}:){7,7}[0-9a-fA-F]{1,4}|([0-9a-fA-F]{1,4}:){1,7}:|([0-9a-fA-F]{1,4}:){1,6}:[0-9a-fA-F]{1,4}|([0-9a-fA-F]{1,4}:){1,5}(:[0-9a-fA-F]{1,4}){1,2}|([0-9a-fA-F]{1,4}:){1,4}(:[0-9a-fA-F]{1,4}){1,3}|([0-9a-fA-F]{1,4}:){1,3}(:[0-9a-fA-F]{1,4}){1,4}|([0-9a-fA-F]{1,4}:){1,2}(:[0-9a-fA-F]{1,4}){1,5}|[0-9a-fA-F]{1,4}:((:[0-9a-fA-F]{1,4}){1,6})|:((:[0-9a-fA-F]{1,4}){1,7}|:)|fe80:(:[0-9a-fA-F]{0,4}){0,4}%[0-9a-zA-Z]{1,}|::(ffff(:0{1,4}){0,1}:){0,1}((25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])\.){3,3}(25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])|([0-9a-fA-F]{1,4}:){1,4}:((25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])\.){3,3}(25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9]))$/',
			],
			'email' => [
				'class' => MatchValidator::class,
				'pattern' => '/^[a-zA-Z0-9!#$%&\'*+\\/=?^_`{|}~-]+(?:\.[a-zA-Z0-9!#$%&\'*+\\/=?^_`{|}~-]+)*@(?:[a-zA-Z0-9](?:[a-zA-Z0-9-]*[a-zA-Z0-9])?\.)+[a-zA-Z0-9](?:[a-zA-Z0-9-]*[a-zA-Z0-9])?$/',
			],
			'fullemail' => [
				'class' => MatchValidator::class,
				'pattern' => '/^[^@]*<[a-zA-Z0-9!#$%&\'*+\\/=?^_`{|}~-]+(?:\.[a-zA-Z0-9!#$%&\'*+\\/=?^_`{|}~-]+)*@(?:[a-zA-Z0-9](?:[a-zA-Z0-9-]*[a-zA-Z0-9])?\.)+[a-zA-Z0-9](?:[a-zA-Z0-9-]*[a-zA-Z0-9])?>$/',
			],
			'method' => MethodValidator::class,
		]);
	}

	public function getValidator(string $id) {
		$ret = $this->component->get($id, false);
		if($ret) return $ret;
		else trigger_error("$id 验证器不存在", E_USER_ERROR);
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

			if($scene === null || $scene === '' || empty($rule['scene'])) {
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