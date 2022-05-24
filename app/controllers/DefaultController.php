<?php
namespace app\controllers;

use app\ws\Demo as WsDemo;
use app\ws\Monitor as WsMonitor;
use fwe\base\Controller;
use fwe\base\Module;
use fwe\curl\Request;
use fwe\db\IEvent;
use fwe\web\RequestEvent;
use fwe\web\StaticController;

class DefaultController extends Controller {
	public function actionIndex() {
		ob_start();
		ob_implicit_flush(false);
		echo '<style type="text/css">
body{margin:0;padding:10px;font-size:14px;}
p{margin:0;line-height:20px;}
span.doc{color:#000;}
span.doc:before{content:"-";color:gray;margin:0 2em;}
em{color:gray;font-style:normal;}
a{color:blue;text-decoration:none;}
a:hover{color:#F60;}
</style>';
		$this->help(\Fwe::$app);
		return ob_get_clean();
	}
	private function help(Module $app, array $defaultRoutes = []) {
		$defaultRoutes[$app->route . $app->defaultRoute] = trim($app->route ?? '', '/');
		
		$moduleIds = array_keys($app->getModules(false));
		sort($moduleIds, SORT_REGULAR);
		foreach($moduleIds as $id) {
			$this->help($app->getModule($id), $defaultRoutes);
		}
		
		ksort($app->controllerMap, SORT_REGULAR);
		
		foreach($app->controllerMap as $id => $controller) {
			$class = $controller['class'] ?? $controller;
			if(is_subclass_of($class, 'fwe\base\Controller')) {
				$object = \Fwe::createObject($controller, [
					'id' => $id,
					'module' => $app
				]); /* @var \fwe\base\Controller $object */
				if($object instanceof StaticController) {
					$this->print(rtrim($object->route, '/'), 'Static');
				} else {
					$reflection = new \ReflectionClass($object);
					$defaultRoutes[$object->route . $object->defaultAction] = trim($object->route ?? '', '/');
					foreach($object->actionMap as $id2 => $action) {
						$class = $action['class'] ?? $action;
						if(is_subclass_of($class, 'fwe\base\Action')) {
							$this->print("{$object->route}$id2", $this->parseDocCommentSummary(empty($action['method']) ? new \ReflectionClass($class) : $reflection->getMethod($action['method'])), $defaultRoutes);
						} else {
							$this->print("{$object->route}$id2", $this->asFormatColor("\"$class\"没有继承\"fwe\base\Action\"", self::FG_RED), $defaultRoutes);
						}
					}
				}
			} else {
				$this->print("{$app->route}$id", "\"$class\"没有继承\"fwe\base\Controller\"", $defaultRoutes);
			}
		}
	}
	const ROUTE_LEN = 32;
	private function print(string $route, string $msg, array $defaultRoutes = []) {
		$defRoute = $route;
		while(isset($defaultRoutes[$defRoute])) {
			$defRoute = $defaultRoutes[$defRoute];
		}
		echo '<p>';
		if($route !== $defRoute) {
			echo '<a href="/', $defRoute, '"><span class="prefix">', $defRoute, '</span><em>', substr($route, strlen($defRoute)), '</em></a>';
		} else {
			echo '<a href="/', $route, '"><span class="full">', $route, '</span></a>';
		}
		if($msg) echo '<span class="doc">' , $msg , '</span>';
		echo "</p>\n";
	}
	private function parseDocCommentSummary($reflection) {
		$docLines = preg_split('/\R/u', $reflection->getDocComment());
		if(isset($docLines[1])) {
			return trim($docLines[1], "\t *");
		}
		return '';
	}
	public function actionWs(RequestEvent $request) {
		$request->webSocket();
	}
	public function actionWsDemo(RequestEvent $request) {
		$request->webSocket(WsDemo::class);
	}
	public function actionWsMonitor(RequestEvent $request) {
		$request->webSocket(WsMonitor::class);
	}
	public function actionEmpty(RequestEvent $request, bool $isReturn = true) {
		if($isReturn) {
			return __METHOD__;
		} else {
			$request->getResponse()->end(__METHOD__);
		}
	}
	public function beforeActionInfo(RequestEvent $request, bool $isToFile = true) {
		$request->isToFile = $isToFile;
		return true;
	}
	public function actionInfo(RequestEvent $request, bool $isChunk = true) {
		$response = $request->getResponse();
		$response->setContentType('text/plain; charset=utf-8');
		if($isChunk) {
			$response->write(json_encode($request, JSON_PRETTY_PRINT));
			$response->write("\r\n\r\n");
			$response->end(json_encode($response, JSON_PRETTY_PRINT));
		} else {
			$response->end(json_encode($request, JSON_PRETTY_PRINT) . "\r\n\r\n" . json_encode($response, JSON_PRETTY_PRINT));
		}
	}
	public function actionTables(RequestEvent $request) {
		$t = microtime(true);
		$request->data = $db = db()->pop();
		$db->asyncQuery("SHOW TABLES", ['style'=>IEvent::FETCH_COLUMN_ALL])
		->asyncQuery("SELECT TABLE_SCHEMA, (DATA_LENGTH+INDEX_LENGTH) as TABLE_SPACE FROM information_schema.TABLES GROUP BY TABLE_SCHEMA", ['style'=>IEvent::FETCH_ALL])
		->asyncQuery("SHOW GLOBAL VARIABLES LIKE '%timeout%'", ['type'=>IEvent::TYPE_OBJ, 'style'=>IEvent::FETCH_ALL])
		->goAsync(function($tables, $sleep, $variables) use($t, $request, $db) {
			$t = microtime(true) - $t;
			$response = $request->getResponse();
			$response->setContentType('text/plain; charset=utf-8');
			$response->end(json_encode(compact('tables', 'sleep', 'variables', 't'), JSON_PRETTY_PRINT));
			$request->data = null;
			$db->push();
		}, function($data) use($t, $request, $db) {
			$t = microtime(true) - $t;
			$response = $request->getResponse();
			$response->setContentType('text/plain; charset=utf-8');
			$response->end(json_encode(compact('data', 't'), JSON_PRETTY_PRINT));
			$request->data = null;
			$db->push();
			return false;
		});
		$request->onFree(function(RequestEvent $req) {
			if(!$req->data) return;

			$req->data->remove();
		});
	}
	public function actionRedis(RequestEvent $request, bool $isAsync = false) {
		if($isAsync) {
			$redis = redis()->pop()->beginAsync();
			$redis->setAsyncKey('keys')
			->keys('*')
			->setAsyncKey('cmdInfo')
			->commandInfo("keys", "info")
			->get('inc')
			->incrby('inc', 1)
			->goAsync(function($data, $keys, $cmdInfo, $get, $incr) use($redis, $request) {
				$request->getResponse()->json(compact('keys', 'cmdInfo', 'get', 'incr'));
				$redis->push();
			}, function($data) use($redis, $request) {
				$request->getResponse()->setStatus(500)->json($data);
				$redis->push();
			});
		} else {
			$db = redis()->pop();
			$request->getResponse()->json([
				'keys' => $db->keys('*'),
				'cmdInfo' => $db->commandInfo("keys", "info"),
			]);
			$db->push($db);
		}
	}
	public function actionAttach(RequestEvent $request) {
		$response = $request->getResponse();
		$response->setContentDisposition('FWE构架首页PHP代码.php', false);
		$response->sendFile(INFILE);
	}
	
	const CURL_COUNT = 5;
	public function actionCurl(RequestEvent $request) {
		$request->data = ['key'=>[],'val'=>[]];
		for($i=0; $i<self::CURL_COUNT; $i++) {
			$req = new Request('https://www.baidu.com/#'.$i);
			$req->addHeader('index', $i);
			curl()->make($req, function($res, $req) use($request) {
				$res = $res->properties;
				$req = $req->properties;
				$request->data['val'][] = compact('req', 'res');
				if(count($request->data['val']) == self::CURL_COUNT) {
					$response = $request->getResponse();
					$response->setContentType('application/json; charset=utf-8');
					$response->end(json_encode($request->data, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
					$request->data = null;
				}
			});
			$request->data['key'][] = $req->resKey;
		}
		$request->onFree(function(RequestEvent $req) {
			if(!$req->data) return;
			
			foreach($req->data['key'] as $key) {
				curl()->cancel($key);
			}
		});
	}
	
	public function actionTimeout(RequestEvent $request, int $timeout = 5) {
		\Fwe::$app->events++;
		$request->data = $event = new \Event(\Fwe::$base, -1, \Event::TIMEOUT, function() use($request) {
			$request->getResponse()->end('Timeout Completed');
			$request->data = null;
			\Fwe::$app->events--;
		});
		$event->addTimer($timeout);
		$request->onFree(function(RequestEvent $request) {
			if(!$request->data) return;
			
			\Fwe::$app->events--;
			$request->data->delTimer();
		});
	}
	
	public function actionCache(RequestEvent $request, int $expire = 5) {
		$request->data = [];
		
		cache()->get(
			'redis',
			function($value) use($request) {
				if($request->data === null) return;
				
				$request->data['redis'] = $value;
				
				if(count($request->data) == 2) {
					$request->getResponse()->json($request->data);
				}
			},
			function(callable $ok) {
				$t = microtime(true);
				$redis = redis()->pop()->beginAsync();
				$redis->setAsyncKey('keys')
				->keys('*')
				->goAsync(function($keys) use($redis, $ok, $t) {
					$t = microtime(true) - $t;
					echo "redis(OK): $t\n";
					$redis->push();
					$ok(compact('keys', 't'));
				}, function($e) use($redis, $ok, $t) {
					$t = microtime(true) - $t;
					echo "redis(ERR): $t\n";
					$redis->push();
					$ok([
						't' => $t,
						'msg' => $e->getMessage(),
					]);
				});
			},
			$expire
		);
		
		cache('cache2')->get(
			'mysql',
			function($value) use($request) {
				if($request->data === null) return;
				
				$request->data['mysql'] = $value;
				
				if(count($request->data) == 2) {
					$request->getResponse()->json($request->data);
				}
			},
			function(callable $ok) {
				$t = microtime(true);
				$db = db()->pop();
				$db->asyncQuery("SHOW TABLES", ['style'=>IEvent::FETCH_COLUMN_ALL])
				->asyncQuery("SELECT TABLE_SCHEMA, (DATA_LENGTH+INDEX_LENGTH) as TABLE_SPACE FROM information_schema.TABLES GROUP BY TABLE_SCHEMA", ['style'=>IEvent::FETCH_ALL])
				->asyncQuery("SHOW GLOBAL VARIABLES LIKE '%timeout%'", ['type'=>IEvent::TYPE_OBJ, 'style'=>IEvent::FETCH_ALL])
				->goAsync(function($tables, $sleep, $variables) use($t, $db, $ok) {
					$t = microtime(true) - $t;
					echo "mysql(OK): $t\n";
					$db->push();
					$ok(compact('tables', 'sleep', 'variables', 't'));
				}, function($data, $e) use($t, $db, $ok) {
					$t = microtime(true) - $t;
					echo "mysql(ERR): $t\n";
					$db->push();
					$ok([
						't' => $t,
						'msg' => $e->getMessage(),
						'data' => $data,
					]);
					return false;
				});
			},
			$expire
		);
	}
}
