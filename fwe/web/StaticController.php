<?php
namespace fwe\web;

use fwe\base\Controller;
use fwe\base\Exception;
use fwe\traits\TplView;
use fwe\utils\FileHelper;
use fwe\base\TsVar;

class StaticController extends Controller {
	use TplView;
	
	public $path;
	public $username = '', $password = '';
	public $isIndex = true, $isDav = false;
	public $defaults = ['index.html', 'index.htm', 'default.html', 'default.htm'];
	protected $_authOK;

	/**
	 * @var TsVar
	 */
	protected $_lock;
	
	public function init() {
		parent::init();
		
		if($this->path) {
			$this->path = \Fwe::getAlias($this->path);
			if(substr($this->path, -1) === '/') {
				FileHelper::mkdir($this->path);
			}
		} else {
			throw new Exception('path property is not empty');
		}
		
		$this->_authOK = function($user, $pass, callable $ok) {
			$ok($user === $this->username && $pass === $this->password);
		};
		
		$this->_lock = new TsVar("__dav:lock:{$this->route}__");
		
		// $this->replaceWhiteSpace = "\n";
	}
	
	public function getAction(string $id, array &$params) {
		$params['file'] = $id;
		
		return parent::getAction($this->isDav ? 'dav' : 'index', $params);
	}
	
	public function actionIndex(RequestEvent $request, string $file, string $key = 'name', string $sort = 'asc', bool $isJson = false) {
		$path = $this->path . $file;
		
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
				foreach(FileHelper::list($path) as $f) {
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
		if($request->method === 'PUT') {
			if($file === '' || substr($file, -1) === '/') {
				return false;
			} else {
				if($request->isAuth($this->_authOK)) {
					$path = $this->path . $file;
				} else {
					$path = '/dev/null';
				}
				$fp = fopen($path, 'w');
				$request->setFp($fp);
				return $fp !== false;
			}
		} else {
			return true;
		}
	}
	
	public function actionDav(RequestEvent $request, string $file) {
		$response = $request->getResponse();
		$response->headers['DAV'] = [
			'1,2',
			'<http://apache.org/dav/propset/fs/1>',
		];
		$response->headers['MS-Author-Via'] = 'DAV';
		
		if(!$request->isAuth($this->_authOK)) return;
		
		$path = $this->path . $file;
		switch($request->method) {
			case 'OPTIONS':
				$response->headers['Access-Control-Allow-Methods'] = 'OPTIONS,HEAD,GET,PUT,DELETE,MKCOL,PROPFIND,MOVE,LOCK,UNLOCK';
				$response->headers['Content-Type'] = 'httpd/unix-directory';
				$response->end();
				break;
			case 'HEAD':
				$stat = stat($path);
				if($stat) {
					$response->headers['Last-Modified'] = gmdate('D, d-M-Y H:i:s T', $stat['mtime']);
					$response->headers['ETag'] = sprintf('%xT-%xO', $stat['mtime'], $stat['size']);
					$response->headers['Accept-Ranges'] = 'bytes';
					$response->headers['Expires'] = gmdate('D, d-M-Y H:i:s T', time() + 3600);
					$response->headers['Cache-Control'] = ['must-revalidate', 'public', 'max-age=3600'];
					$response->headSend($stat['size']);
					$response->end();
				} else {
					$response->setStatus(404)->end('Not Found');
				}
				break;
			case 'GET':
				$response->sendFile($path);
				break;
			case 'PUT':
				if($request->bodylen === $request->bodyoff) {
					$response->setStatus(201)->end('Created');
				} else {
					unlink($path);
					$response->setStatus(507)->end('Insufficient Storage');
				}
				break;
			case 'DELETE':
				if($file !== '' && (substr($file, -1) === '/' ? rmdir($path) : unlink($path))) {
					$response->end();
				} else {
					$response->setStatus(400)->end('Bad Request');
				}
				break;
			case 'MKCOL':
				$isCreated = substr($file, -1) === '/' && FileHelper::mkdir($path);
				if($isCreated) {
					$response->setStatus(201)->end($this->renderView('@fwe/views/dav/mkcol.tpl', compact('file', 'isCreated')));
				} else {
					$response->setStatus(400)->end('Bad Request');
				}
				break;
			case 'PROPFIND':
				if($request->mode === RequestEvent::BODY_MODE_XML && ($file === '' || substr($file, -1) === '/')) {
					if(isset($request->post['prop']['resourcetype'], $request->post['prop']['getcontentlength'], $request->post['prop']['getetag'], $request->post['prop']['getlastmodified'], $request->post['prop']['executable'])) {
						$files = [];
						foreach(FileHelper::list($path) as $f) {
							$p = $path . $f;
							$files[is_dir($p) ? "$f/" : $f] = stat($p);
						}
						$response->setContentType('application/xml');
						$response->setStatus(207)->end($this->renderView('@fwe/views/dav/propfind.tpl', ['file' => $file, 'files' => $files, 'stat' => stat($path)]));
					} elseif(isset($request->post['prop']['quota-available-bytes'], $request->post['prop']['quota-used-bytes'])) {
						$total = disk_total_space($path);
						$available = disk_free_space($path);
						$used = $total - $available;
						
						$response->setContentType('application/xml');
						$response->setStatus(207)->end($this->renderView('@fwe/views/dav/propfind-quota.tpl', compact('file', 'available', 'used')));
					} else {
						$response->setStatus(500)->end('Request Params Error');
					}
				} else {
					$response->setStatus(500)->end('Request Params Error');
				}
				break;
			case 'MOVE':
				if(isset($request->headers['Host'], $request->headers['Destination']) && ($uri = @parse_url($request->headers['Destination'])) && isset($uri['host'], $uri['path'])) {
					if($request->headers['Host'] === $uri['host'] . (isset($uri['port']) ? ":{$uri['port']}" : null) && !strncmp($uri['path'], "/{$this->route}", $n = strlen($this->route) + 1) && rename($path, $this->path . substr($uri['path'], $n))) {
						$response->end();
					} else {
						$response->setStatus(500)->end('Request Params Error');
					}
				} else {
					$response->setStatus(500)->end('Request Params Error');
				}
				break;
			case 'LOCK':
				$isWrite = isset($request->post['locktype']['write']);
				$isOk = $this->_lock->lock($file, 1800, $isWrite);
				// printf("  LOCK %s %s %s %d\n", $isWrite ? 'write' : 'read', $file, $isOk ? 'true' : 'false', $this->_lock->count());
				$token = md5($this->route . $file);
				$response->headers['Dav-Lock'] = ($isOk ? 'true' : 'false');
				$response->headers['Lock-Token'] = "<opaquelocktoken:{$token}>";
				$response->setContentType('application/xml');
				$response->end($this->renderView('@fwe/views/dav/lock.tpl', compact('token')));
				break;
			case 'UNLOCK':
				$isWrite = isset($request->post['locktype']['write']);
				$isOk = $this->_lock->unlock($file, $isWrite);
				// printf("UNLOCK %s %s %s %d\n", $isWrite ? 'write' : 'read', $file, $isOk ? 'true' : 'false', $this->_lock->count());
				$response->headers['Dav-Unlock'] = ($isOk ? 'true' : 'false');
				$response->setStatus(204)->end();
				break;
			default:
				$response->setStatus(405)->end('Method Not Allowed: ' . $request->method);
				break;
		}
	}
}
