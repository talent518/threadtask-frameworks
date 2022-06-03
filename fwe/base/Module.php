<?php
namespace fwe\base;

use fwe\traits\MethodProperty;
use fwe\utils\FileHelper;

/**
 * @property-read string $route 控制器的路由
 * @property string $basePath
 * @property string $viewPath
 * @property string $layoutView
 * @property callable $layoutCall
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
	 * @var integer
	 */
	public $controllerLevel;

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
		
		$this->controllerMap += \Fwe::$config->getOrSet("{$this->controllerNamespace}:map", function() {
			$path = \Fwe::getAlias('@' . str_replace('\\', '/', $this->controllerNamespace));
			$rets = [];
			$this->scandir($rets, $this->controllerNamespace, $path, '');
			return $rets;
		});
		
		$this->controllerLevel = \Fwe::$config->getOrSet("{$this->controllerNamespace}:level", function() {
			$level = 1;
			foreach(array_keys($this->controllerMap) as $key) {
				$n = substr_count($key, '/') + 1;
				if($n > $level) {
					$level = $n;
				}
			}
			return $level;
		});
	}
	
	protected function scandir(array &$maps, string $ns, string $path, string $prefix) {
		foreach(FileHelper::list($path) as $file) {
			$_path = "$path/$file";
			if(is_dir($_path)) {
				$id = trim(preg_replace_callback('/[A-Z]/', function ($matches) {
					return '-' . strtolower($matches[0]);
				}, $file), '-');
				$this->scandir($maps, "$ns\\$file", $_path, "{$prefix}{$id}/");
			} elseif(strlen($file) > 14 && substr($file, - 14) === 'Controller.php') {
				$id = trim(preg_replace_callback('/[A-Z]/', function ($matches) {
					return '-' . strtolower($matches[0]);
				}, substr($file, 0, - 14)), '-');
				$file = substr($file, 0, - 4);
				$maps["{$prefix}{$id}"] = "$ns\\$file";
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

	public function beforeAction(Action $action, array &$params = []): bool {
		return $this->module->beforeAction($action, $params);
	}

	public function afterAction(Action $action, array &$params = []) {
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
		if($route === '') {
			$route = $this->defaultRoute;
		}

		$_route = $route;

		$prefix = '';
		$i = 0;
		do {
			if(strpos($route, '/') !== false) {
				list($id, $route) = explode('/', $route, 2);
			} else {
				$id = $route;
				$route = '';
			}
			
			$ID = $prefix . $id;
			
			if(($module = $this->getModule($ID)) !== null) {
				return $module->getAction($route, $params);
			} elseif(isset($this->controllerObjects[$ID])) {
				return $this->controllerObjects[$ID]->getAction($route, $params);
			} elseif(isset($this->controllerMap[$ID])) {
				$controller = $this->controllerMap[$ID];
				
				$class = $controller['class'] ?? $controller;
				if(is_string($class) && is_subclass_of($class, 'fwe\base\Controller')) {
					return \Fwe::createObject($controller, [
						'id' => $ID,
						'module' => $this
					])->getAction($route, $params);
				} else {
					unset($this->controllerMap[$ID]);
					throw new Exception("{$class}不是fwe\base\Controller的子类");
				}
			} else {
				$prefix .= "$id/";
			}
		} while((++ $i) < $this->controllerLevel && $route !== '');
		
		throw new RouteException($_route, "没有发现路由\"$_route\"");
	}
	
	private $_viewPath, $_basePath;
	
	public function getBasePath() {
		if ($this->_basePath === null) {
			$class = new \ReflectionClass($this);
			$this->_basePath = dirname($class->getFileName());
		}
		
		return $this->_basePath;
	}
	
	public function setBasePath(string $path) {
		$this->_basePath = \Fwe::getAlias($path);
	}

	public function getViewPath(): string {
		if ($this->_viewPath === null) {
			$this->_viewPath = $this->getBasePath() . '/views';
			if($this->module && !is_dir($this->_viewPath)) {
				$viewPath = $this->module->getViewPath() . "/{$this->id}";
				if(is_dir($viewPath)) {
					$this->_viewPath = $viewPath;
				} else {
					\Fwe::$app->warn("请在创建 {$this->_viewPath} 或 {$viewPath} 目录并在其中添加需要的视图文件");
				}
			}
		}

		return $this->_viewPath;
	}
	
	public function setViewPath(string $path) {
		$this->_viewPath = \Fwe::getAlias($path);
	}
	
	private $_layoutView, $_layoutCall;
	
	public function getLayoutView() {
		return $this->_layoutView ?: ($this->module ? $this->module->getLayoutView() : null);
	}
	
	public function setLayoutView(string $view) {
		$this->_layoutView = $view;
	}
	
	public function getLayoutCall() {
		return $this->_layoutCall ?: ($this->module ? $this->module->getLayoutCall() : null);
	}
	
	public function setLayoutCall(callable $call) {
		$this->_layoutCall = $call;
	}
}
