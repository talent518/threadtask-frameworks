<?php
namespace fwe\console;

use fwe\base\Module;

class HelpController extends Controller {

	/**
	 * 当前帮助信息
	 *
	 * @param array $__params__
	 * @param string $route
	 */
	public function actionIndex(array $__params__, string $route = '') {
		if($route === '') {
			// unset($this->module->controllerMap[$this->id]);
			// $this->print($this, "{$this->route}", "当前帮助信息");
			// $this->print($this, "{$this->route}var", "查看环境变量");
			$this->help(\Fwe::$app);
		} else {
			$params = $__params__;
			$params['__params__'] = $__params__;
			$action = \Fwe::$app->getAction($route, $params);

			if(is_array($action->callback)) {
				$reflection = new \ReflectionMethod($action->callback[0], $action->callback[1]);
			} elseif(is_object($action->callback) && ! $action->callback instanceof \Closure) {
				$reflection = new \ReflectionMethod($action->callback, '__invoke');
			} else {
				$reflection = new \ReflectionFunction($action->callback);
			}
			
			$this->formatColor("函数名：", self::BG_CYAN);
			$this->formatColor(($action->funcName??$reflection->getName()) . "()\n", self::FG_YELLOW);
			$this->formatColor("文件名：", self::BG_CYAN);
			$this->formatColor($reflection->getFileName() . ":" . $reflection->getStartLine() . "-" . $reflection->getEndLine() . "\n", self::FG_YELLOW);
			
			$docLines = preg_split('/\R/u', $reflection->getDocComment());
			if(count($docLines) > 2) {
				echo "\n";
				$this->formatColor("函数描述：", self::BG_CYAN);
				echo "\n";
				for($i = 1; $i < count($docLines) - 1; $i ++) {
					$this->formatColor(trim($docLines[$i], "\t *"), self::FG_CYAN);
					echo "\n";
				}
			}

			if($reflection->getNumberOfParameters() === 0) {
				$this->formatColor("\n无任何参数，可直接执行\n", self::FG_BLUE);
				return;
			} else {
				echo "\n";
				$this->formatColor("函数参数值：", self::BG_CYAN);
				echo "\n";
			}
			$i = 0;
			foreach($reflection->getParameters() as $param) { /* @var $param \ReflectionParameter */
				if(PHP_VERSION_ID >= 80000) {
					$class = $param->getType();
					$isClass = $class !== null && ! $param->getType()->isBuiltin();
				} else {
					$class = $param->getClass();
					$isClass = $class !== null;
				}
				if($isClass) {
					echo $class->getName(), 'sdf';
				} elseif($param->isArray()) {
					echo 'array';
				} elseif($param->isCallable()) {
					echo 'callable';
				} else {
					echo $param->getType();
				}
				echo ' ';
				if($param->isPassedByReference()) {
					echo '&';
				}
				echo '$', $name = $param->getName();
				if($param->isOptional()) {
					if($param->isDefaultValueAvailable()) {
						echo "\n  默认值: ";
						$this->formatColor(str_replace("\n", "\n        ", var_export($param->getDefaultValue(), true)), self::FG_RED);
						echo "\n    新值: ";
					}
					if($param->isDefaultValueConstant()) {
						echo "\n   默认值: ";
						$this->formatColor($param->getDefaultValueConstantName(), self::FG_RED);
						echo "\n    新值: ";
					}
				} else {
					echo "\n    新值: ";
				}
				$this->beginColor(self::FG_GREEN);
				if(array_key_exists($name, $params)) {
					$this->formatColor(str_replace("\n", "\n        ", var_export($params[$name], true)), self::FG_RED);
				} elseif(array_key_exists($i, $params)) {
					$this->formatColor(str_replace("\n", "\n        ", var_export($params[$i], true)), self::FG_RED);
				} elseif($isClass) {
					echo "new $class";
				} else {
					echo '?';
				}
				$this->endColor();
				echo PHP_EOL;
			}
		}
	}

	/**
	 * 查看环境变量
	 */
	public function actionVar() {
		echo '$_SERVER = ';
		var_export($_SERVER);
		echo PHP_EOL, PHP_EOL;

		echo 'Fwe::$aliases = ';
		var_export(\Fwe::$aliases->all());
		echo PHP_EOL, PHP_EOL;

		echo 'Fwe::$classMap = ';
		var_export(\Fwe::$classMap->all());
		echo PHP_EOL, PHP_EOL;

		echo 'Fwe::$classAlias = ';
		var_export(\Fwe::$classAlias->all());
		echo PHP_EOL;
	}

	private function help(Module $app, array $defaultRoutes = []) {
		$defaultRoutes[$app->route . $app->defaultRoute] = trim($app->route ?? '', '/');

		$moduleIds = array_keys($app->getModules(false));
		sort($moduleIds, SORT_REGULAR);
		foreach($moduleIds as $id) {
			$this->help($app->getModule($id), $defaultRoutes);
		}

		$path = \Fwe::getAlias('@' . str_replace('\\', '/', $app->controllerNamespace));
		$this->scandir($app, $app->controllerNamespace, $path, '');

		ksort($app->controllerMap, SORT_REGULAR);

		foreach($app->controllerMap as $id => $controller) {
			$class = $controller['class'] ?? $controller;
			if(is_subclass_of($class, 'fwe\base\Controller')) {
				$object = \Fwe::createObject($controller, [
					'id' => $id,
					'module' => $app
				]); /* @var \fwe\base\Controller $object */
				$reflection = new \ReflectionClass($object);
				$defaultRoutes[$object->route . $object->defaultAction] = trim($object->route ?? '', '/');
				foreach($object->actionMap as $id2 => $action) {
					$class = $action['class'] ?? $action;
					if(is_subclass_of($class, 'fwe\base\Action')) {
						$reflection = new \ReflectionClass($class);
						$this->print($object, "{$object->route}$id2", $this->parseDocCommentSummary($reflection), $defaultRoutes);
					} else {
						$this->print($object, "{$object->route}$id2", $this->asFormatColor("\"$class\"没有继承\"fwe\base\Action\"", self::FG_RED), $defaultRoutes);
					}
				}
				foreach($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
					if(preg_match('/^action([A-Z][A-Za-z0-9]*)$/', $method->getName(), $matches)) {
						$id2 = trim(preg_replace_callback('/[A-Z]/', function ($matches) {
							return '-' . strtolower($matches[0]);
						}, $matches[1]), '-');
						$this->print($object, "{$object->route}$id2", $this->parseDocCommentSummary($method), $defaultRoutes);
					}
				}
			} else {
				$this->print($app, "{$app->route}$id", $this->asFormatColor("\"$class\"没有继承\"fwe\base\Controller\"", self::FG_RED), $defaultRoutes);
			}
		}
	}

	const ROUTE_LEN = 32;

	private function print($app, string $route, string $msg, array $defaultRoutes = []) {
		$defRoute = $route;
		while(isset($defaultRoutes[$defRoute])) {
			$defRoute = $defaultRoutes[$defRoute];
		}
		if($route !== $defRoute) {
			echo $defRoute;
			$this->formatColor(substr($route, strlen($defRoute)), self::FG_CYAN);
			printf("%s %s\n", str_pad('', self::ROUTE_LEN - strlen($route), ' ', STR_PAD_RIGHT), $msg);
		} else {
			printf("%s %s\n", str_pad(trim($route, '/'), self::ROUTE_LEN, ' ', STR_PAD_RIGHT), $msg);
		}
	}

	private function scandir(Module $app, string $ns, string $path, string $prefix) {
		if(! is_dir($path))
			return;
		$dh = @opendir($path);
		if($dh === false)
			return;
		while(($file = readdir($dh)) !== false) {
			if($file === '.' || $file === '..')
				continue;

			$_path = "$path/$file";
			if(is_dir($_path)) {
				$id = trim(preg_replace_callback('/[A-Z]/', function ($matches) {
					return '-' . strtolower($matches[0]);
				}, $file), '-');
				$this->scandir($app, "$ns\\$file", $_path, "{$prefix}{$id}/");
			} elseif(strlen($file) > 14 && substr($file, - 14) === 'Controller.php') {
				$id = trim(preg_replace_callback('/[A-Z]/', function ($matches) {
					return '-' . strtolower($matches[0]);
				}, substr($file, 0, - 14)), '-');
				$file = substr($file, 0, - 4);
				$app->controllerMap["{$prefix}{$id}"] = "$ns\\$file";
			}
		}
		closedir($dh);
	}

	private function parseDocCommentSummary($reflection) {
		$docLines = preg_split('/\R/u', $reflection->getDocComment());
		if(isset($docLines[1])) {
			return trim($docLines[1], "\t *");
		}
		return '';
	}
}
