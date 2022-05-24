<?php
namespace fwe\base;

use fwe\traits\MethodProperty;

/**
 * @author abao
 * @property-read string $route 控制器的路由
 * @property string $basePath
 * @property string $viewPath
 * @property string $layoutView
 * @property callable $layoutCall
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
		
		\Fwe::debug(get_called_class(), $this->_route, false);
	}
	
	public function __destruct() {
		\Fwe::debug(get_called_class(), $this->_route, true);
	}

	public function init() {
		$this->module->controllerObjects[$this->id] = $this;
		
		$class = get_class($this);
		$this->actionMap += \Fwe::$config->getOrSet($class, function() use($class) {
			$rets = [];
			foreach((new \ReflectionClass($class))->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
				if(!$method->isStatic() && strpos($method->getName(), '_') === false && substr($method->getName(), 0 , 6) === 'action') {
					$id = trim(preg_replace_callback('/[A-Z]/', function ($matches) {
						return '-' . strtolower($matches[0]);
					}, substr($method->getName(), 6)), '-');
					
					$rets[$id] = [
						'class' => InlineAction::class,
						'method' => $method->getName()
					];
				}
			}
			
			return $rets;
		});
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
		if($id === '') {
			$id = $this->defaultAction;
		}
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

		if(isset($this->actionObjects[$id])) {
			return $this->actionObjects[$id];
		}

		if(isset($this->actionMap[$id])) {
			$action = $this->actionMap[$id];
			$class = $action['class'] ?? $action;
			if(is_string($class) && is_subclass_of($class, 'fwe\base\IAction')) {
				return \Fwe::createObject($action, [
					'id' => $id,
					'controller' => $this
				]);
			} else {
				unset($this->actionMap[$id]);
				throw new Exception("{$class}不是fwe\base\IAction的子类");
			}
		} else {
			throw new RouteException($id, "没有发现操作\"$id\"");
		}
	}
	
	public function getViewFile(string $view) {
		if(!strncmp($view, '@', 1)) {
			$file = \Fwe::getAlias($view);
		} elseif(!strncmp($view, '//', 2)) {
			$file = \Fwe::$app->getViewPath() . '/' . ltrim($view, '/');
		} elseif(!strncmp($view, '/', 1)) {
			$file = $this->module->getViewPath() . '/' . ltrim($view, '/');
		} else {
			$file = $this->module->getViewPath() . "/{$this->id}/$view";
		}
		
		return $file;
	}
	
	public function render(string $view, array $params = [], ?callable $ok = null, ... $args) {
		return $this->renderContent($this->renderView($view, $params), $ok, ...$args);
	}
	
	private $_layoutView, $_layoutCall;
	
	public function getLayoutView() {
		return $this->_layoutView ?: $this->module->getLayoutView();
	}
	
	public function setLayoutView(string $view) {
		$this->_layoutView = $view;
	}
	
	public function getLayoutCall() {
		return $this->_layoutCall ?: $this->module->getLayoutCall();
	}
	
	public function setLayoutCall(callable $call) {
		$this->_layoutCall = $call;
	}
	
	public function renderContent(string $content, ?callable $ok = null, ... $args) {
		$view = $this->getLayoutView();
		if($view) {
			$call = $this->getLayoutCall();
			if($call) {
				if($ok === null) {
					throw new Exception('Layout中存在需要异步获取数据时：$ok参数不能为空');
				}

				call_user_func(
					$call,
					function(array $params) use($ok, $content, $view) {
						if(array_key_exists('content', $params)) {
							\Fwe::$app->warn('Overflow params of content key', 'view');
						}
						$params['content'] = $content;
						$ok($this->renderView($view, $params));
					},
					... $args
				);
			} elseif($ok) {
				$ok($this->renderView($view, ['content'=>$content]));
			} else {
				return $this->renderView($view, ['content'=>$content]);
			}
		} elseif($ok) {
			$ok($content);
		} else {
			return $content;
		}
	}
	
	public function renderView(string $view, array $params = []) {
		$file = $this->getViewFile($view);
		$ext = pathinfo($file, PATHINFO_EXTENSION);
		if($ext === '') {
			$ext = $this->getViewExtension();
			if($ext === 'php') {
				$file = "{$file}.php";
			} else {
				$_file = "{$file}.{$ext}";
				if($ext !== 'php' && !is_file($_file)) {
					$_file2 = "{$file}.php";
					if(is_file($_file2)) {
						$_file = $_file2;
						$ext = 'php';
					}
					unset($_file2);
				}
				$file = $_file;
				unset($_file);
			}
		}
		if($ext !== 'php') {
			$file = $this->buildView($file, $view);
		}
		return $this->renderPhpFile($file, $params);
	}
	
	protected function getViewExtension() {
		return 'php';
	}
	
	protected function buildView(string $viewFile, string $view) {
		throw new Exception("Override protected buildView method for implements $viewFile convert to php file");
	}
	
	public function renderPhpFile(string $__file__, array $__params__ = []): string {
		opcache_invalidate($__file__);
		
		$__obLevel__ = ob_get_level();
		ob_start();
		ob_implicit_flush(false);
		extract($__params__, EXTR_OVERWRITE);
		try {
			require $__file__;
			return ob_get_clean();
		} catch (\Throwable $e) {
			while (ob_get_level() > $__obLevel__) {
				if (!@ob_end_clean()) {
					ob_clean();
				}
			}
			throw $e;
		}
	}
}
