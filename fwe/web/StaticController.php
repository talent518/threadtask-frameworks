<?php
namespace fwe\web;

use fwe\base\Controller;
use fwe\base\Exception;

class StaticController extends Controller {
	public $path;
	public $username = '', $password = '';
	public $isIndex = true, $isDav = false;
	public $defaults = ['index.html', 'index.htm', 'default.html', 'default.htm'];
	protected $_authOK;
	
	public function init() {
		parent::init();
		
		if(!$this->path) {
			throw new Exception('path property is not empty');
		}
		
		$this->_authOK = function($user, $pass, callable $ok) {
			$ok($user === $this->username && $pass === $this->password);
		};
	}
	
	public function getAction(string $id, array &$params) {
		$params['file'] = $id;
		
		return parent::getAction($this->isDav ? 'dav' : 'index', $params);
	}
	
	public function actionIndex(RequestEvent $request, string $file, string $key = 'name', string $sort = 'asc', bool $isJson = false) {
		$path = \Fwe::getAlias($this->path . $file);
		
		if(substr($path, -1) === '/') {
			if(is_dir($path)) {
				foreach($this->defaults as $default) {
					$_path = $path . $default;
					if(is_file($_path)) {
						$request->getResponse()->sendFile($_path);
						return;
					}
				}
				
				if(!$this->isIndex) {
					$request->getResponse()->setStatus(404)->end('Not Found');
					return;
				}
				
				$files = [];
				if(($dh = @opendir($path)) !== false) {
					while(($f=readdir($dh)) !== false) {
						if($f === '.' || $f === '..') continue;
						
						$type = null;
						$st = @stat($path . '/' . $f);
						$perms = $this->getperms($st['mode'] ?? 0, $type);
						$files[] = [
							'name' => $f,
							'url' => $type === 'Directory' ? "/{$this->route}{$file}{$f}/" : "/{$this->route}{$file}{$f}",
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
					$request->getResponse()->setStatus(404)->end('Not Found');
					return;
				}
				
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
				
				if($isJson) {
					$request->getResponse()->json($files);
				} else {
					return $this->renderView('@fwe/views/static.php', compact('file', 'files', 'key', 'sort'));
				}
			} else {
				$request->getResponse()->setStatus(404)->end('Not Found');
			}
		} elseif(is_file($path)) {
			$request->getResponse()->sendFile($path);
		} else {
			$request->getResponse()->setStatus(404)->end('Not Found');
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
	
	public function beforeActionDav(RequestEvent $request, string $file) {
		if($file === '') {
			return true;
		} elseif($request->method === 'PUT') {
			if(substr($file, -1) === '/') {
				return false;
			} else {
				if($request->isAuth($this->_authOK)) {
					$path = $this->path . $file;
				} else {
					$path = '/dev/null';
				}
				$fp = fopen($path, 'a');
				$request->setFp($fp);
				return $fp !== false;
			}
		} else {
			return true;
		}
	}
	
	public function actionDav(RequestEvent $request, string $file) {
		$response = $request->getResponse();
		$response->headers['DAV'] = '1,2,3,"<http://apache.org/dav/propset/fs/1>",access-control,version-control,checkout-in-place,version-history,workspace,update,label,working-resource,merge,baseline,version-controlled-collection,extended-mkcol';
		
		if(!$request->isAuth($this->_authOK)) return;
		
		switch($request->method) {
			case 'HEAD':
				$response->end();
				break;
			case 'GET':
				$response->end();
				break;
			case 'PUT':
				if($request->bodylen === $request->bodyoff) {
					$response->setStatus(201)->end('Created');
				} else {
					$response->setStatus(507)->end('Insufficient Storage');
				}
				break;
			case 'MKCOL':
				$response->end();
				break;
			case 'DELETE':
				$response->end();
				break;
			case 'COPY':
				$response->end();
				break;
			case 'MOVE':
				$response->end();
				break;
			case 'ACL':
				$response->end();
				break;
			case 'PROPFIND':
				$response->end();
				break;
			case 'PROPPATCH':
				$response->end();
				break;
			case 'LOCK':
				$response->end();
				break;
			case 'UNLOCK':
				$response->end();
				break;
			case 'OPTIONS':
				$response->headers['Access-Control-Allow-Methods'] = ['HEAD', 'GET', 'PUT', 'MKCOL', 'DELETE', 'COPY', 'MOVE', 'ACL', 'PROPFIND', 'PROPPATCH', 'LOCK', 'UNLOCK', 'OPTIONS'];
				$response->end();
				break;
			default:
				$response->setStatus(405)->end('Method Not Allowed: ' . $request->method);
				break;
		}
		
		echo json_encode(compact('request', 'response'), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), PHP_EOL;
	}
}
