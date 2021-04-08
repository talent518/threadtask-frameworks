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
	 * @var array|TsVar
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
	 * @var TsVar
	 */
	public static $classMap;

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
			static::$classMap[$className] = $classFile;
		} else {
			return;
		}

		include $classFile;
	}

	/**
	 *
	 * @var TsVar
	 */
	public static $classAlias;

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

			method_exists($object, 'init') and $object->init();

			return $object;
		} else {
			throw new Exception('必须存在class键名的数组配置参数才能创建对象：' . @var_export($type, true));
		}
	}

	public static function invoke(callable $callback, array $params, ?string $funcName = null) {
		if(is_array($callback)) {
			$reflection = new \ReflectionMethod($callback[0], $callback[1]);
			$object = is_object($callback[0]) ? $callback[0] : null;
		} elseif(is_object($callback) && ! $callback instanceof \Closure) {
			$reflection = new \ReflectionMethod($callback, '__invoke');
			$object = $callback;
		} else {
			$reflection = new \ReflectionFunction($callback);
			$object = null;
		}

		$isAssoc = false;
		foreach($params as $key => $val) {
			if(is_string($key)) {
				$isAssoc = true;
				break;
			}
		}
		unset($val);

		if($isAssoc) {
			if($reflection instanceof \ReflectionFunction) {
				return $reflection->invokeArgs(static::makeArgs($reflection, $params, $funcName));
			} else {
				return $reflection->invokeArgs($object, static::makeArgs($reflection, $params, $funcName));
			}
		} else {
			if($reflection instanceof \ReflectionFunction) {
				return $reflection->invokeArgs(static::makeArgs($reflection, $params, $funcName));
			} else {
				return $reflection->invokeArgs($object, $params);
			}
		}
	}

	public static function makeArgs(ReflectionFunctionAbstract $reflection, array &$params, ?string $funcName = null) {
		$args = [];
		$i = 0;
		foreach($reflection->getParameters() as $param) { /* @var ReflectionParameter $param */
			$name = $param->getName();
			if(PHP_VERSION_ID >= 80000) {
				$class = $param->getType();
				$isClass = $class !== null && ! $param->getType()->isBuiltin();
			} else {
				$class = $param->getClass();
				$isClass = $class !== null;
			}
			if($isClass) {
				$className = $class->getName();
				if(PHP_VERSION_ID >= 50600 && $param->isVariadic()) {
					$args = array_merge($args, array_values($params));
					break;
				}

				if(array_key_exists($name, $params)) {
					revalue:
					$value = $params[$name];
					if($value instanceof $className) {
						$args[] = $value;
					} elseif(static::$app && static::$app->has($value)) {
						$args[] = static::$app->get($value);
					} else {
						$args[] = static::createObject($value);
					}
					unset($params[$name]);
				} elseif(array_key_exists($i, $params)) {
					$name = $i++;
					goto revalue;
				} elseif(! $param->isOptional()) {
					$args[] = static::createObject($className);
				}
			} elseif(array_key_exists($name, $params)) {
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
	 * @var Application
	 */
	public static $app;

	/**
	 * @var TsVar
	 */
	public static $config;
	
	/**
	 * @var EventBase
	 */
	public static $base;

	public static function boot() {
		$name = (defined('THREAD_TASK_NAME') ? preg_replace('/^(.+)[\d_-]*/', '$1', THREAD_TASK_NAME) : 'main');
		$config = static::$config->getOrSet($name, function () use (&$name) {
			return include static::getAlias('@app/config/' . $name . '.php');
		});
		static::$base = new EventBase();
		static::$app = static::createObject($config);
		static::$app->boot();
		static::$base->dispatch();
		// static::$base->loop(EventBase::LOOP_ONCE);
	}
}

spl_autoload_register('Fwe::autoload', true, true);
Fwe::$aliases = new TsVar('__aliases');
Fwe::$aliases->getOrSet('@fwe', function () {
	return __DIR__;
});
Fwe::$classMap = new TsVar('__classMap');
Fwe::$classAlias = new TsVar('__classAlias');
Fwe::$config = new TsVar('__config');

include_once __DIR__ . '/functions.php';
