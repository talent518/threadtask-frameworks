<?php
define('FWE_PATH', __DIR__);

use fwe\base\Exception;
use fwe\base\TsVar;
use fwe\base\Application;

/**
 * getAlias/getRootAlias/setAlias/autoload 来自yii2
 *
 * @author abao
 */
abstract class Fwe {

	/**
	 * 注册目录别名
	 *
	 * @var array
	 * @see getAlias()
	 * @see setAlias()
	 */
	public static $aliases = [
		'@fwe' => __DIR__
	];

	/**
	 * 获取目录别名
	 *
	 * @param string $alias
	 * @param boolean $throwException
	 * @throws Exception
	 * @return string|boolean
	 */
	public static function getAlias(string $alias, bool $throwException = true) {
		if(strpos($alias, '@') !== 0) {
			// not an alias
			return $alias;
		}

		$pos = strpos($alias, '/');
		$root = $pos === false ? $alias : substr($alias, 0, $pos);

		if(isset(static::$aliases[$root])) {
			if(is_string(static::$aliases[$root])) {
				return $pos === false ? static::$aliases[$root] : static::$aliases[$root] . substr($alias, $pos);
			}

			foreach(static::$aliases[$root] as $name => $path) {
				if(strpos($alias . '/', $name . '/') === 0) {
					return $path . substr($alias, strlen($name));
				}
			}
		}

		if($throwException) {
			throw new Exception("无效的目录别名: $alias");
		}

		return false;
	}

	/**
	 * 返回给定别名的根别名部分。
	 * 根别名是以前通过[[@@ setAlias]]注册的别名。如果给定的别名与多个根别名匹配，则返回最长的一个。
	 *
	 * @param string $alias
	 *        	别名
	 * @return string|bool 返回根别名，如果不存在根别名则返回false
	 */
	public static function getRootAlias($alias) {
		$pos = strpos($alias, '/');
		$root = $pos === false ? $alias : substr($alias, 0, $pos);

		if(isset(static::$aliases[$root])) {
			if(is_string(static::$aliases[$root])) {
				return $root;
			}

			foreach(static::$aliases[$root] as $name => $path) {
				if(strpos($alias . '/', $name . '/') === 0) {
					return $name;
				}
			}

			unset($path);
		}

		return false;
	}

	/**
	 * 注册一个目录别名
	 *
	 * @param string $alias
	 *        	别名（例如“@fwe”）。它必须以'@'字符开头。它可能包含正斜杠“/”，在[[＠＠ getAlias]]执行别名转换时用作边界字符。
	 * @param string $path
	 *        	与别名对应的路径。如果为空，则将删除别名。将修剪尾随的“/”和“\”字符。
	 *        	
	 * @see getAlias()
	 */
	public static function setAlias($alias, $path) {
		if(strncmp($alias, '@', 1)) {
			$alias = '@' . $alias;
		}
		$pos = strpos($alias, '/');
		$root = $pos === false ? $alias : substr($alias, 0, $pos);
		if($path !== null) {
			$path = strncmp($path, '@', 1) ? rtrim($path, '\\/') : static::getAlias($path);
			if(! isset(static::$aliases[$root])) {
				if($pos === false) {
					static::$aliases[$root] = $path;
				} else {
					static::$aliases[$root] = [
						$alias => $path
					];
				}
			} elseif(is_string(static::$aliases[$root])) {
				if($pos === false) {
					static::$aliases[$root] = $path;
				} else {
					static::$aliases[$root] = [
						$alias => $path,
						$root => static::$aliases[$root]
					];
				}
			} else {
				static::$aliases[$root][$alias] = $path;
				krsort(static::$aliases[$root]);
			}
		} elseif(isset(static::$aliases[$root])) {
			if(is_array(static::$aliases[$root])) {
				unset(static::$aliases[$root][$alias]);
			} elseif($pos === false) {
				unset(static::$aliases[$root]);
			}
		}
	}

	/**
	 *
	 * @var array
	 */
	public static $classMap = [];

	/**
	 * 类的自动加载
	 *
	 * @param string $className
	 *        	不带前导反斜杠“\”的完全限定类名
	 */
	public static function autoload($className) {
		if(isset(static::$classMap[$className])) {
			$classFile = static::$classMap[$className];
			if(strpos($classFile, '@') === 0) {
				$classFile = static::getAlias($classFile);
			}
		} elseif(strpos($className, '\\') !== false) {
			$classFile = static::getAlias('@' . str_replace('\\', '/', $className) . '.php', false);
			if($classFile === false || ! is_file($classFile)) {
				return;
			}
			// static::$classMap[$className] = $classFile;
		} else {
			return;
		}

		include $classFile;
	}

	/**
	 *
	 * @var array
	 */
	public static $classAlias = [];

	/**
	 * 创建对象的入口
	 *
	 * @param string|callable|array $type
	 * @param array $params
	 * @return object
	 */
	public static function createObject($type, array $params = []) {
		recreated:
		if(is_string($type)) {
			$type = [
				'class' => $type
			];
		} else if(!is_array($type)) {
			throw new Exception('不支持的对象创建类型：' . gettype($type));
		}

		if(isset($type['class'])) {
			$class = $type['class'];
			unset($type['class']);

			if(isset(static::$classAlias[$class])) {
				$class = static::$classAlias[$class];
				if(is_array($class)) {
					$type = array_merge($type, $class);
					goto recreated;
				} elseif(! is_string($class)) {
					throw new Exception('不支持的对象创建类型：' . gettype($class));
				}
			}
			
			$type += $params;

			$reflection = new ReflectionClass($class);

			$constructor = $reflection->getConstructor();
			if($constructor) {
				$object = $reflection->newInstanceArgs(static::makeArgs($constructor, $type, "$class::__construct"));
			} else {
				$object = $reflection->newInstanceWithoutConstructor();
			}

			foreach($type as $prop => $value) {
				$object->$prop = $value;
			}

			static::setVars($object, $type);

			method_exists($object, 'init') and $object->init();

			return $object;
		} else {
			throw new Exception('必须存在class键名的数组配置参数才能创建对象：' . @var_export($type, true));
		}
	}

	public static function invoke(callable $callback, array $params, ?string $funcName = null) {
		$isAssoc = false;
		foreach($params as $key => $val) {
			if(is_string($key)) {
				$isAssoc = true;
				break;
			}
		}
		unset($key, $val);

		if($isAssoc) {
			if(is_array($callback)) {
				$reflection = new ReflectionMethod($callback[0], $callback[1]);
				$object = is_object($callback[0]) ? $callback[0] : null;
			} elseif(is_object($callback) && ! ($callback instanceof \Closure)) {
				$reflection = new ReflectionMethod($callback, '__invoke');
				$object = $callback;
			} else {
				$reflection = new ReflectionFunction($callback);
				$object = null;
			}

			$params = static::makeArgs($reflection, $params, $funcName);

			$reflection = null;
		}

		return call_user_func_array($callback, $params);
	}

	public static function makeArgs(ReflectionFunctionAbstract $reflection, array &$params, ?string $funcName = null) {
		$__params__ = $params;
		$args = [];
		$i = 0;
		foreach($reflection->getParameters() as $param) { /* @var ReflectionParameter $param */
			$name = $param->getName();
			if($name === '__params__') {
				$args[] = $__params__;
				continue;
			}

			if(array_key_exists($name, $params)) {
				$args[] = $params[$name];
				unset($params[$name]);
			} elseif(array_key_exists($i, $params)) {
				$args[] = $params[$i];
				unset($params[$i]);
				$i++;
			} elseif($param->isDefaultValueAvailable()) {
				$args[] = $param->getDefaultValue();
			} elseif(! $param->isOptional()) {
				if($funcName === null)
					$funcName = $reflection->getName();
				throw new Exception("当调用 \"$funcName()\" 时 缺少必选参数 \"$name\"。");
			}
		}

		return $args;
	}

	/**
	 * 获取对象的public属性
	 * 
	 * @return array
	 */
	public static function getVars(object $obj) {
		return get_object_vars($obj);
	}

	public static function setVars(object $obj, array $properties) {
		foreach($properties as $prop => $value) {
			$obj->$prop = $value;
		}
	}

	/**
	 * @var Application
	 */
	public static $app;

	/**
	 * @var TsVar
	 */
	public static $config;
	
	/**
	 * @var \EventBase
	 */
	public static $base;

	/**
	 * @var string
	 */
	public static $name;
	
	/**
	 * @var array
	 */
	public static $names;
	
	public static function boot() {
		if(defined('THREAD_TASK_NAME')) {
			static::$names = explode(':', THREAD_TASK_NAME);
			static::$name = array_shift(static::$names);
		} else {
			static::$name = 'main';
			static::$names = [];
		}

		static::$base = new EventBase();
		static::createObject(static::$config->getOrSet(static::$name, function () {
			return include static::getAlias('@app/config/' . static::$name . '.php');
		}))->boot();
		static::$base->dispatch();

		if(!defined('THREAD_TASK_NAME')) {
			$sig = static::$app->exitSig();
			task_wait($sig);
		}
	}
}

spl_autoload_register('Fwe::autoload', true, true);
Fwe::$config = new TsVar('__config');

include_once __DIR__ . '/functions.php';
