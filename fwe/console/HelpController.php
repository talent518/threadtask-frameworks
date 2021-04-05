<?php
namespace fwe\console;

use fwe\base\Module;
use fwe\base\Controller;

class HelpController extends Controller {

	public function actionIndex() {
		unset($this->module->controllerMap[$this->id]);

		$this->print($this, "{$this->route}", "当前帮助信息");
		$this->print($this, "{$this->route}var", "查看环境变量");
		$this->help(\Fwe::$app);
	}

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
						$this->print($object, "{$object->route}$id2", "\033[31m\"$class\"没有继承\"fwe\base\Action\"\033[0m", $defaultRoutes);
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
				$this->print($app, "{$app->route}$id", "\033[31m\"$class\"没有继承\"fwe\base\Controller\"\033[0m", $defaultRoutes);
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
			echo $defRoute, "\033[30m", substr($route, strlen($defRoute)), "\033[0m";
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
