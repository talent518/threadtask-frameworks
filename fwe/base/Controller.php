<?php
namespace fwe\base;

class Controller {

	/**
	 *
	 * @var string
	 */
	public $id;

	/**
	 *
	 * @var Module
	 */
	public $module;

	/**
	 *
	 * @var string
	 */
	public $defaultAction = 'index';

	/**
	 *
	 * @var array
	 */
	public $actionMap = [];

	/**
	 *
	 * @var array
	 */
	public $actionObjects = [];

	public function __construct(string $id, Module $module) {
		$this->id = $id;
		$this->module = $module;
	}

	public function init() {
		$this->module->controllerObjects[$this->id] = $this;
	}

	/**
	 *
	 * @param string $id
	 * @param array $param
	 */
	public function runAction(string $id, array $params) {
		if($id === '')
			$id = $this->defaultAction;

		$id = preg_replace('/[_-]+/', '-', $id);
		$id = trim(preg_replace_callback('/[A-Z]/', function ($matches) {
			return '-' . strtolower($matches[0]);
		}, $id), '-');

		if(! isset($this->actionMap[$id])) {
			$methodName = 'action' . preg_replace_callback('/-([a-z])/i', function ($matches) {
				return ucfirst($matches[1]);
			}, ucfirst($id));

			if(method_exists($this, $methodName)) {
				$method = new \ReflectionMethod($this, $methodName);
				if($method->isPublic() && $method->getName() === $methodName) {
					$this->actionMap[$id] = [
						'class' => InlineAction::class,
						'method' => $methodName
					];
				} else {
					$this->actionMap[$id] = false;
				}
			} else {
				$this->actionMap[$id] = false;
			}
		}

		$action = $this->actionMap[$id];
		if($action) {
			$class = $action['class'] ?? $action;
			if(is_subclass_of($class, 'fwe\base\Action')) {
				return \Fwe::createObject($action, [
					'id' => $id,
					'controller' => $this
				])->run($params);
			} else {
				throw new Exception("{$class}不是fwe\base\Action的子类");
			}
		} else {
			throw new RouteException($id, "没有发现操作\"$id\"");
		}
	}
}
