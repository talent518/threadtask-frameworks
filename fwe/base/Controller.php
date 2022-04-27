<?php
namespace fwe\base;

use fwe\traits\MethodProperty;

/**
 * @author abao
 * @property-read string $route 控制器的路由
 */
class Controller {
	use MethodProperty;

	/**
	 * @var string
	 */
	public $id;

	/**
	 * @var Module
	 */
	public $module;

	/**
	 * @var string
	 */
	public $defaultAction = 'index';

	/**
	 * @var array
	 */
	public $actionMap = [];

	/**
	 * @var array
	 */
	public $actionObjects = [];

	private $_route;

	public function __construct(string $id, Module $module) {
		$this->id = $id;
		$this->module = $module;
		$this->_route = "{$module->route}{$id}/";
	}

	public function init() {
		$this->module->controllerObjects[$this->id] = $this;
	}

	public function getRoute() {
		return $this->_route;
	}

	public function beforeAction(Action $action, array $params = []): bool {
		if($action instanceof InlineAction) {
			$methodName = 'before' . ucfirst($action->method);
			if(method_exists($this, $methodName)) {
				$method = new \ReflectionMethod($this, $methodName);
				if($method->isPublic() && $method->getName() === $methodName && !\Fwe::invoke([$this, $methodName], $params, get_class($this) . "::$methodName")) {
					return false;
				}
			}
		}
		return $this->module->beforeAction($action, $params);
	}

	public function afterAction(Action $action, array $params = []) {
		$this->module->afterAction($action, $params);
		if($action instanceof InlineAction) {
			$methodName = 'after' . ucfirst($action->method);
			if(method_exists($this, $methodName)) {
				$method = new \ReflectionMethod($this, $methodName);
				if($method->isPublic() && $method->getName() === $methodName) {
					\Fwe::invoke([$this, $methodName], $params, get_class($this) . "::$methodName");
				}
			}
		}
	}
	
	public function splitId(string &$id, array &$params) {
		return false;
	}

	/**
	 * 根据路由获取Action对象
	 *
	 * @param string $id
	 * @param array $params
	 * @return Action
	 */
	public function getAction(string $id, array &$params) {
		if($id === '') {
			$id = $this->defaultAction;
		}
		
		if($this->splitId($id, $params)) {
			if(strpos($id, '/') !== false) {
				list($id, $route) = explode('/', $id, 2);
			} else {
				$route = '';
			}
		} else {
			$route = '';
		}

		$params['route__'] = $this->_route . $id;
		$params['__route'] = $route;

		$id = preg_replace('/[_-]+/', '-', $id);
		$id = trim(preg_replace_callback('/[A-Z]/', function ($matches) {
			return '-' . strtolower($matches[0]);
		}, $id), '-');

		if(isset($this->actionObjects[$id])) {
			return $this->actionObjects[$id];
		}

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
				}
			}
		}

		$action = $this->actionMap[$id] ?? false;
		if($action) {
			$class = $action['class'] ?? $action;
			if(is_string($class) && is_subclass_of($class, 'fwe\base\IAction')) {
				return \Fwe::createObject($action, [
					'id' => $id,
					'controller' => $this
				]);
			} else {
				$this->actionMap[$id] = 1;
				throw new Exception("{$class}不是fwe\base\IAction的子类");
			}
		} else {
			throw new RouteException($id, "没有发现操作\"$id\"");
		}
	}
}
