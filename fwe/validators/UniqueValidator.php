<?php
namespace fwe\validators;

use fwe\base\Model;
use fwe\fibers\MySQLFiber;

class UniqueValidator extends IValidator {
	public $message = '{attribute} 已存在';

	public function validate(Model $model, bool $isPerOne = false, bool $isOnly = false) {
		$ret = 0;
		foreach($this->attributes as $attr) {
			$value = $model->{$attr};
			if(($isPerOne && $model->hasError($attr)) || $this->isSkipOnEmpty($value)) {
				continue;
			} else {
				$ret ++;
			}
		}

		if($ret) {
			return function(callable $ok, $db) use($model) {
				if($db instanceof MySQLFiber) {
					(new \Fiber(function() use($ok, $db, $model) {
						$exists = $model->unique($db, $this->attributes);
						$ok($exists ? 1 : 0);
					}))->start();
				} else {
					$model->unique($db, $this->attributes, function(int $exists, ?string $error = null) use($model, $ok) {
						if($exists > 0) {
							foreach($this->attributes as $attr) {
								$model->addError($attr, strtr($this->message, ['{attribute}'=>$model->getLabel($attr)]));
							}
						}
						if($exists < 0) {
							$ok($exists, $error);
						} else {
							$ok($exists ? 1 : 0);
						}
					});
				}
			};
		} else {
			return $ret;
		}
	}
}
