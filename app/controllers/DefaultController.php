<?php
namespace app\controllers;

use fwe\base\Controller;
use fwe\web\RequestEvent;
use fwe\db\IEvent;
use fwe\curl\Request;
use app\ws\Demo;

class DefaultController extends Controller {
	private function getperms(int $mode, ?string &$type = null) {
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
				$st = stat($path . '/' . $f);
				$perms = $this->getperms($st['mode'], $type);
				$files[] = [
					'name' => $f,
					'url' => $type === 'Directory' ? "/{$route__}{$__route}/$f/" : "/static{$__route}/$f",
					'size' => $st['size'],
					'perms' => $perms,
					'type' => $type,
					'atime' => $st['atime'],
					'mtime' => $st['mtime'],
					'ctime' => $st['ctime'],
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

		ob_start();
		ob_implicit_flush(false);
?><!DOCTYPE html>
<html lang="zh-cn">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="referrer" content="origin" />
    <meta http-equiv="Cache-Control" content="no-transform" />
    <meta http-equiv="Cache-Control" content="no-siteapp" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title><?=$__route?>/</title>
    <style type="text/css">
    body{margin:0;padding:5px;}
    table{border:1px #ccc solid;border-width:1px 0 0 1px;border-spacing:0;margin:0 auto;}
    th,td{border:1px #ccc solid;padding:5px;}
    td{border-width:0 1px 1px 0;}
    th{border-width:0 1px 2px 0;}
    </style>
</head>
<body>
<table>
	<thead>
		<tr><?php
		$titles = [
			'name' => 'Name',
			'size' => 'Size',
			'type' => 'Type',
			'perms' => 'Perm',
			'atime' => 'Time of last access',
			'mtime' => 'Time of last modification',
			'ctime' => 'Time of last modification',
		];
		foreach($titles as $k=>$t):
			if($k === $key):
				?><th><a href="?key=<?=$k?><?=($sort === 'asc' ? '&sort=desc' : null)?>"><?=$t?><?=($sort === 'asc' ? '↑' : '↓')?></a></th><?php
			else:
				?><th><a href="?key=<?=$k?>"><?=$t?></a></th><?php
			endif;
		endforeach;
		?></tr>
	</thead>
	<tbody><?php
	if($__route !== ''):
		?><tr><td colspan="7"><a href="<?=strrpos($__route, '/') ? '..' : '/'?>">..</a></td></tr><?php
	endif;
	foreach($files as $file):
		?><tr>
			<td><a href="<?=$file['url']?>"><?=$file['name']?></a></td>
			<td><?=$file['size']?></td>
			<td><?=$file['type']?></td>
			<td><?=$file['perms']?></td>
			<td><?=date('Y-m-d H:i:s', $file['atime'])?></td>
			<td><?=date('Y-m-d H:i:s', $file['mtime'])?></td>
			<td><?=date('Y-m-d H:i:s', $file['ctime'])?></td>
		</tr><?php
	endforeach;
	?></tbody>
</table>
</body>
</html>
<?php
		return ob_get_clean();
	}
	public function actionWs(RequestEvent $request) {
		$request->webSocket();
	}
	public function actionWsDemo(RequestEvent $request) {
		$request->webSocket(Demo::class);
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
		$db->asyncQuery("SHOW TABLES", ['style'=>IEvent::FETCH_COLUMN_ALL])->asyncQuery("SELECT TABLE_SCHEMA, (DATA_LENGTH+INDEX_LENGTH) as TABLE_SPACE FROM information_schema.TABLES GROUP BY TABLE_SCHEMA", ['style'=>IEvent::FETCH_ALL])->asyncQuery("SHOW GLOBAL VARIABLES LIKE '%timeout%'", ['type'=>IEvent::TYPE_OBJ, 'style'=>IEvent::FETCH_ALL])->goAsync(function($tables, $sleep, $variables) use($t, $request) {
			$t = microtime(true) - $t;
			$response = $request->getResponse();
			$response->setContentType('text/plain; charset=utf-8');
			$response->end(json_encode(compact('tables', 'sleep', 'variables', 't'), JSON_PRETTY_PRINT));
			$request->data = null;
		}, function($data) use($t, $request) {
			$t = microtime(true) - $t;
			$response = $request->getResponse();
			$response->setContentType('text/plain; charset=utf-8');
			$response->end(json_encode(compact('data', 't'), JSON_PRETTY_PRINT));
			$request->data = null;
			return false;
		});
		$request->onFree(function(RequestEvent $req) {
			if(!$req->data) return;

			$req->data->cancel();
		});
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
