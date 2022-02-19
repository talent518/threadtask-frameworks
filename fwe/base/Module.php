<?php
namespace fwe\base;

use fwe\traits\MethodProperty;

/**
 * @property-read string $route 控制器的路由
 */
class Module {
	use MethodProperty;

	/**
	 * 控制器索引
	 *
	 * @var array
	 */
	public $controllerMap = [];

	/**
	 * 控制器命名空间
	 *
	 * @var string
	 */
	public $controllerNamespace;

	/**
	 * 默认路由
	 *
	 * @var string
	 */
	public $defaultRoute = 'default';

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
	 * 子模块的配置对象
	 *
	 * @var Component
	 */
	protected $_modules;
	
	/**
	 * @var string
	 */
	protected $_route;

	public function __construct(string $id, Module $module = null) {
		$this->id = $id;
		$this->module = $module;
		$this->_modules = \Fwe::createObject(Component::class);

		if($this->module !== null) $this->_route = "{$this->module->route}{$this->id}/";
	}
	
	public function getRoute() {
		return $this->_route;
	}

	public function init() {
		if($this->controllerNamespace === null) {
			$class = get_class($this);
			if($class === self::class) {
				throw new Exception("模块{$class}没有设置controllerNamespace属性。");
			}
			if(($pos = strrpos($class, '\\')) !== false) {
				$this->controllerNamespace = substr($class, 0, $pos) . '\\controllers';
			}
		}
	}

	/**
	 * 根据模块名判断模块配置或对象是否存在
	 *
	 * @param string $id
	 * @return boolean
	 */
	public function hasModule(string $id) {
		return $this->_modules->has($id);
	}

	/**
	 * 根据模块名获取模块对象
	 *
	 * @param string $id
	 * @param bool $isMake
	 * @return object
	 */
	public function getModule(string $id, bool $isMake = true) {
		return $this->_modules->get($id, $isMake, ['module' => $this]);
	}

	/**
	 * 根据模块名设置模块配置或对象
	 *
	 * @param string $id
	 * @param mixed $value
	 * @param bool $isFull
	 */
	public function setModule(string $id, $value, bool $isFull = true) {
		$this->_modules->set($id, $value, $isFull);
	}

	/**
	 * 获取所有模块配置或对象列表
	 *
	 * @param bool $isObject
	 * @return array
	 */
	public function getModules(bool $isObject = true) {
		return $this->_modules->all($isObject);
	}

	/**
	 * 一次设置多个组件配置或对象
	 *
	 * @param array $modules
	 */
	public function setModules(array $modules) {
		foreach($modules as $id => $value) {
			$this->_modules->set($id, $value);
		}
	}

	public function beforeAction(Action $action, array $params = []): bool {
		return $this->module->beforeAction($action, $params);
	}

	public function afterAction(Action $action, array $params = []) {
		$this->module->afterAction($action, $params);
	}

	/**
	 * 控制器对象缓存
	 *
	 * @var array|Controller
	 */
	public $controllerObjects = [];

	/**
	 * 根据路由获取Action对象
	 *
	 * @param string $route
	 * @param array $params
	 * @return Action
	 */
	public function getAction(string $route, array &$params) {
		// double slashes or leading/ending slashes may cause substr problem
		$route = trim($route, '/');
		if(strpos($route, '//') !== false) {
			throw new Exception("路由\"$route\"中包括了//");
		}
		
		if($route === '') {
			$route = $this->defaultRoute;
		}

		$_route = $route;

		$prefix = '';
		do {
			if(strpos($route, '/') !== false) {
				list($id, $route) = explode('/', $route, 2);
			} else {
				$id = $route;
				$route = '';
			}

			$id = preg_replace('/[_-]+/', '-', $id);
			$id = trim(preg_replace_callback('/[A-Z]/', function ($matches) {
				return '-' . strtolower($matches[0]);
			}, $id), '-');
			
			$ID = $prefix . $id;

			if(($module = $this->getModule($ID)) !== null) {
				return $module->getAction($route, $params);
			}
			
			if(isset($this->controllerObjects[$ID])) {
				return $this->controllerObjects[$ID]->getAction($route, $params);
			}

			if(! isset($this->controllerMap[$ID])) {
				$className = $this->controllerNamespace . '\\' . ($prefix === '' ? null : str_replace('/', '\\', preg_replace_callback('/-([a-z0-9_])/i', function ($matches) {
					return ucfirst($matches[1]);
				}, $prefix))) . preg_replace_callback('/-([a-z])/i', function ($matches) {
					return ucfirst($matches[1]);
				}, ucfirst($id)) . 'Controller';
				if(strpos($className, '-') === false && class_exists($className)) {
					$this->controllerMap[$ID] = $className;
				}
			}

			$controller = $this->controllerMap[$ID]??false;
			if($controller) {
				$class = $controller['class'] ?? $controller;
				if(is_string($class) && is_subclass_of($class, 'fwe\base\Controller')) {
					return \Fwe::createObject($controller, [
						'id' => $ID,
						'module' => $this
					])->getAction($route, $params);
				} else {
					$this->controllerMap[$ID] = 1;
					throw new Exception("{$class}不是fwe\base\Controller的子类");
				}
			} else {
				$prefix .= "$id/";
			}
		} while(! $controller && $route !== '');
		
		throw new RouteException($_route, "没有发现路由\"$_route\"");
	}
}
