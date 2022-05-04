<?php
namespace app\controllers;

use fwe\base\Controller;
use fwe\web\RequestEvent;
use fwe\db\IEvent;
use fwe\curl\Request;
use app\ws\Demo as WsDemo;
use app\ws\Monitor as WsMonitor;

class DefaultController extends Controller {
	public function splitId(string &$id, array &$params) {
		if($id === '') {
			$id = $this->defaultAction;
			return false;
		} else {
			return !strncasecmp("$id/", 'index/', 6);
		}
	}

	protected function getperms(int $mode, ?string &$type = null) {
		if (($mode & 0xC000) == 0xC000) {
			// Socket
			$info = 's';
			$type = 'Socket';
		} elseif (($mode & 0xA000) == 0xA000) {
			// Symbolic Link
			$info = 'l';
			$type = 'Symbolic Link';
		} elseif (($mode & 0x8000) == 0x8000) {
			// Regular
			$info = '-';
			$type = 'Regular';
		} elseif (($mode & 0x6000) == 0x6000) {
			// Block special
			$info = 'b';
			$type = 'Block special';
		} elseif (($mode & 0x4000) == 0x4000) {
			// Directory
			$info = 'd';
			$type = 'Directory';
		} elseif (($mode & 0x2000) == 0x2000) {
			// Character special
			$info = 'c';
			$type = 'Character special';
		} elseif (($mode & 0x1000) == 0x1000) {
			// FIFO pipe
			$info = 'p';
			$type = 'FIFO pipe';
		} else {
			// Unknown
			$info = 'u';
			$type = 'Unknown';
		}

		// Owner
		$info .= (($mode & 0x0100) ? 'r' : '-');
		$info .= (($mode & 0x0080) ? 'w' : '-');
		$info .= (($mode & 0x0040) ?
				    (($mode & 0x0800) ? 's' : 'x' ) :
				    (($mode & 0x0800) ? 'S' : '-'));

		// Group
		$info .= (($mode & 0x0020) ? 'r' : '-');
		$info .= (($mode & 0x0010) ? 'w' : '-');
		$info .= (($mode & 0x0008) ?
				    (($mode & 0x0400) ? 's' : 'x' ) :
				    (($mode & 0x0400) ? 'S' : '-'));

		// World
		$info .= (($mode & 0x0004) ? 'r' : '-');
		$info .= (($mode & 0x0002) ? 'w' : '-');
		$info .= (($mode & 0x0001) ?
				    (($mode & 0x0200) ? 't' : 'x' ) :
				    (($mode & 0x0200) ? 'T' : '-'));
		return $info;
	}
	public function actionIndex(RequestEvent $request, string $route__, string $__route) {
		if($__route !== '') {
			$__route = preg_replace('/(\/\.\.\/|\/\.\.$)/', '/', "/$__route");
			if($__route === '/') $__route = '';
		}
		$path = \Fwe::getAlias("@app/static{$__route}");
		$files = [];
		if(($dh = @opendir($path)) !== false) {
			while(($f=readdir($dh)) !== false) {
				if($f === '.' || $f === '..') continue;
				
				$type = null;
				$st = @stat($path . '/' . $f);
				$perms = $this->getperms($st['mode'] ?? 0, $type);
				$files[] = [
					'name' => $f,
					'url' => $type === 'Directory' ? "/{$route__}{$__route}/$f/" : "/static{$__route}/$f",
					'size' => $st['size']??0,
					'perms' => $perms,
					'type' => $type,
					'atime' => $st['atime']??0,
					'mtime' => $st['mtime']??0,
					'ctime' => $st['ctime']??0,
				];
			}
			closedir($dh);
		} else {
			$request->getResponse()->setStatus(404);
			return;
		}
		
		$key = ($request->get['key'] ?? 'name');
		$sort = ($request->get['sort'] ?? 'asc');

		if($files) {
			if($key === 'url' || !isset($files[0][$key])) $key = 'name';
			switch($key) {
				case 'size':
				case 'atime':
				case 'mtime':
				case 'ctime':
					$call = function(array $a, array $b) use($key) {return $a[$key] <=> $b[$key];};
					break;
				default:
					$call = function(array $a, array $b) use($key) {return strcmp($a[$key], $b[$key]);};
					break;
			}
			
			if($sort === 'asc') usort($files, $call);
			else usort($files, function(array $a, array $b) use($call) {return -$call($a, $b);});
		}

		if(isset($request->get['json'])) {
			$request->getResponse()->setContentType('application/json; charset=utf-8');
			return json_encode($files, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
		}

		return $this->renderFile($this->getViewFile('index'), get_defined_vars());
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
}
