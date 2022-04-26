<?php
namespace fwe\web;

use fwe\base\Action;
use fwe\base\RouteException;
use fwe\base\TsVar;

class Application extends \fwe\base\Application {
	public $controllerNamespace = 'app\controllers';

	/**
	 * 
	 * @var string
	 */
	public $host = '0.0.0.0';
	
	/**
	 * @var int
	 */
	public $port = 5000;

	protected $_isReq = false;

	public $gcTimes = 120;

	public function init() {
		parent::init();
		
		$i = 0;
		$memsize = 0;
		$name = defined('THREAD_TASK_NAME') ? THREAD_TASK_NAME : 'main';
		$this->signalEvent(function() use(&$i, &$memsize, $name) {
			if($this->_isReq && $this->isEmptyReq() && !$this->_running) {
				\Fwe::$base->exit();
			}
			if(++$i > $this->gcTimes) {
				$i = 0;
				gc_collect_cycles();
				$size = memory_get_usage() - $memsize;
				$memsize += $size;
				echo "$name memory: $size\n";
			}
		});
	}

	public function signalHandler(int $sig) {
		parent::signalHandler($sig);

		if($this->_sock) {
			@socket_shutdown($this->_sock, 2);
			@socket_close($this->_sock);
			$this->_sock = null;
		}

		if(!$this->_isReq || $this->isEmptyReq()) {
			\Fwe::$base->exit();
		}
	}
	
	public $maxIdleSeconds = 10.0;
	public $maxRecvSeconds = 30.0;
	public $maxRunSeconds = 30.0;
	public $timeoutStatus = 408;
	public $timeoutType = 'text/plain';
	public $timeoutRecvData = 'Request Recv Time-out';
	public $timeoutRunData = 'Request Run Time-out';
	protected function isEmptyReq() {
		$count = 0;
		
		$time = microtime(true);
		foreach($this->_reqEvents as $reqEvent) { /* @var $reqEvent RequestEvent */
			if(($reqEvent->runTime && $reqEvent->runTime + $this->maxRunSeconds < $time) ||
				($reqEvent->runTime === null && $reqEvent->recvTime && $reqEvent->recvTime + $this->maxRecvSeconds < $time)) {
				$response = $reqEvent->getResponse();
				$response->setStatus($this->timeoutStatus);
				$response->setContentType($this->timeoutType);
				$response->end($reqEvent->runTime === null ? $this->timeoutRecvData : $this->timeoutRunData);
				$count ++;
			} elseif(!$reqEvent->isHTTP && $reqEvent->time + $this->maxIdleSeconds < $time) {
				$reqEvent->free();
			} else {
				$count ++;
			}
		}
		
		return !$count;
	}
	
	public function strerror(string $msg, bool $isThrow = true) {
		$errno = socket_last_error();
		$error = socket_strerror($errno);
		socket_clear_error();
		if($errno === SOCKET_EINTR) return;
		
		$e = new \Exception("$msg: $error", $errno);
		
		if($isThrow) {
			throw $e;
		} else {
			echo "$e\n";
		}
	}
	
	/**
	 * @var int
	 */
	protected $_fd;
	
	/**
	 * @var resource|\Socket
	 */
	protected $_sock;
	
	/**
	 * @var array|TsVar
	 */
	protected $_reqVars = [];
	
	/**
	 * @var integer
	 */
	public $maxThreads = 4, $backlog = 128, $maxWsGroups = 2;
	
	public $isToFile = true;
	
	/**
	 * @var TsVar
	 */
	protected $_connStatVar, $_curlStatVar;

	protected $_statEvent, $_lstEvent;

	public function listen() {
		($this->_sock = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) or $this->strerror('socket_create');
		@socket_set_option($this->_sock, SOL_SOCKET, SO_REUSEADDR, 1) or $this->strerror('socket_set_option', false);
		@socket_bind($this->_sock, $this->host, (int) $this->port) or $this->strerror('socket_bind');
		@socket_listen($this->_sock, $this->backlog) or $this->strerror('socket_listen');
		
		$this->_fd = socket_export_fd($this->_sock);
		$this->_connStatVar = new TsVar("conn:stat");
		$this->_curlStatVar = new TsVar("curl:stat");
		
		$statFile = \Fwe::getAlias('@app/runtime/stat.log');
		$connFile = \Fwe::getAlias('@app/runtime/conn.log');
		$curlFile = \Fwe::getAlias('@app/runtime/curl.log');
		
		$n = $ns = $ne = 0;
		$this->_statEvent = new \Event(\Fwe::$base, -1, \Event::TIMEOUT | \Event::PERSIST, function() use(&$statFile, &$connFile, &$curlFile, &$n, &$ns, &$ne) {
			$n2 = $this->stat('conns', 0);
			$n3 = 0;
			for($i = 0; $i < $this->maxWsGroups; $i++) {
				$n3 += $this->_wsVars[$i]->count();
			}
			$n4 = $this->stat('success', 0);
			$n5 = $this->stat('error', 0);
			$n = $n2 - $n;
			$ns = $n4 - $ns;
			$ne = $n5 - $ne;
			$time = date('Y-m-d H:i:s');
			$rc = $this->stat('realConns', 0);
			file_put_contents($statFile, "[$time] $rc current connects, $n connects/second, $n3 accepts, $ns successes, $ne errors\n", FILE_APPEND);
			$conns = implode(' ', $this->_connStatVar->all());
			file_put_contents($connFile, "$conns\n", FILE_APPEND);
			$curls = implode(' ', $this->_curlStatVar->all());
			file_put_contents($curlFile, "$curls\n", FILE_APPEND);
			$n = $n2;
			$ns = $n4;
			$ne = $n5;
		});
		$this->_statEvent->addTimer(1);
		
		$reqTasks = [];
		$this->_lstEvent = new \Event(\Fwe::$base, $this->_fd, \Event::READ | \Event::PERSIST, function() use(&$reqTasks) {
			$addr = $port = null;
			$fd = socket_accept_ex($this->_fd, $addr, $port);
			if(!$fd) return;

			@socket_set_option($fd, SOL_SOCKET, SO_LINGER, ['l_onoff'=>1, 'l_linger'=>1]) or $this->strerror('socket_set_option', false);
			$fd = socket_export_fd($fd, true);
			
			$this->stat('realConns');
			$key = $this->stat('conns');

			$i = null;
			$this->_connStatVar->minmax($i);
			$this->_connStatVar->inc($i, 1);
			$reqVar = $this->_reqVars[$i]; /* @var $reqVar TsVar */
			$reqVar[$key] = [$fd, $addr, $port];

			$reqVar->write();
			
			if(empty($reqTasks[$i])) {
				$reqTasks[$i] = 1;
				create_task(\Fwe::$name . ":req:$i", INFILE, [$i]);
			}
		});
		$this->_lstEvent->add();
		
		for($i = 0; $i < $this->maxWsGroups; $i++) {
			$this->_wsVars[$i] = new TsVar("__ws{$i}__", 0, null, true);
		}

		for($i = 0; $i < $this->maxThreads; $i++) {
			$this->_reqVars[$i] = new TsVar("req:$i", 0, null, true);
			$this->_connStatVar[$i] = 0;
		}
		
		echo "Listened on {$this->host}:{$this->port}\n";
		
		return true;
	}
	
	protected $_reqIndex = 0, $_reqEvent, $_reqEvents = [];

	/**
	 * @var TsVar
	 */
	protected $_wsVar;
	
	public $wsPingDelay = 30;
	public function ws(int $index) {
		$this->_reqIndex = $index;
		$this->_wsVar = new TsVar("__ws{$index}__", 0, null, true);

		$this->_reqEvent = $this->_wsVar->newReadEvent(function() {
			if(($n = $this->_wsVar->read(128)) === false) return;
			
			for($i=0; $i<$n; $i++) {
				$key = null;
				list($fd, $addr, $port, $doClass) = $this->_wsVar->shift(true, $key);

				$this->_reqEvents[$key] = \Fwe::createObject(WsEvent::class, compact('fd', 'addr', 'port', 'key', 'doClass'));
			}
		});
		$this->_reqEvent->add();
		
		$ping = pack('CC', 0x8a, 4) . 'ping';
		$this->_statEvent = new \Event(\Fwe::$base, -1, \Event::TIMEOUT | \Event::PERSIST, function() use($ping) {
			$this->sendWs($ping);
		});
		$this->_statEvent->addTimer($this->wsPingDelay);
	}
	
	protected $_wsTasks = [];
	public function addWs(int $index, $key, array $args) {
		if($index >= $this->maxWsGroups) $index = $this->maxWsGroups - 1;
		elseif($index < 0) $index = 0;
		
		if(empty($this->_wsTasks[$index])) {
			$this->_wsTasks[$index] = 1;
			$name = \Fwe::$name . ":ws:$index";
			\Fwe::$config->getOrSet($name, function() use($name, $index) {
				return create_task($name, INFILE, [$index]);
			});
		}

		$wsVar = $this->_wsVars[$index];
		$wsVar[$key] = $args;
		$wsVar->write();
	}
	
	public function sendWs($data, $doClass = false) {
		if($doClass) {
			foreach($this->_reqEvents as $ev) {
				if(is_subclass_of($ev->doClass, $doClass)) {
					$ev->send($data);
				}
			}
		} else {
			foreach($this->_reqEvents as $ev) {
				if(!$ev->doClass) {
					$ev->send($data);
				}
			}
		}
	}
	
	public function setReqEvent(int $key, $val = false) {
		if($val) {
			if(isset($this->_reqEvents[$key])) {
				echo "{$key} is exists\n";
			} else {
				$this->_reqEvents[$key] = $val;
			}
		} else {
			unset($this->_reqEvents[$key]);
		}
	}

	public function decConn() {
		$this->_connStatVar->inc($this->_reqIndex, -1);
		$this->stat('realConns', -1);
	}

	/**
	 * @var array|TsVar
	 */
	protected $_wsVars = [];

	public $keepAlive = 10;
	public function req(int $index) {
		$this->_isReq = true;
		$this->_reqIndex = $index;
		$this->_reqVars = new TsVar("req:$index", 0, null, true);
		$this->_connStatVar = new TsVar("conn:stat");

		for($i = 0; $i < $this->maxWsGroups; $i++) {
			$this->_wsVars[$i] = new TsVar("__ws{$i}__", 0, null, true);
		}
		
		$this->_reqEvent = $this->_reqVars->newReadEvent(function() use($index) {
			if(($n = $this->_reqVars->read(128)) === false) return;

			for($i=0; $i<$n; $i++) {
				$key = null;
				list($fd, $addr, $port) = $this->_reqVars->shift(true, $key);
				
				$keepAlive = microtime(true) + $this->keepAlive;
				$this->_reqEvents[$key] = \Fwe::createObject(RequestEvent::class, compact('fd', 'addr', 'port', 'key', 'keepAlive'));
			}
		});
		$this->_reqEvent->add();
	}
	
	public $maxBodyLen = 8*1024*1024;
	public function beforeAction(Action $action, array $params = []): bool {
		$request = $params['request'];
		if($request->bodylen > $this->maxBodyLen) {
			$request->getResponse()->setStatus(403)->end('<h1>Request Entity Too Large</h1>');
			return false;
		} else {
			return true;
		}
	}
	
	public $statics = [];
	public function getAction(string $route, array &$params) {
		foreach($this->statics as $prefix => $path) {
			if(substr($prefix, -1) === '/') {
				$n = strlen($prefix);
				if(strncmp($route, $prefix, $n)) {
					continue;
				}
				$file = \Fwe::getAlias($path . substr($route, $n));
			} else {
				if($route !== $prefix) {
					continue;
				}
				$file = $file = \Fwe::getAlias($path);
			}

			if(is_file($file)) {
				return \Fwe::createObject(StaticAction::class, compact('route', 'prefix', 'path', 'file', 'params'));
			} else {
				throw new RouteException($route, 'Not Found');
			}
		}
		return parent::getAction($route, $params);
	}
	
	public function isService() {
		return true;
	}
	
	public function isWeb() {
		return true;
	}
	
	public function boot() {
		$method = array_shift(\Fwe::$names) ?: 'listen';
		
		$params = [];
		for($i = 1; $i < $_SERVER['argc']; $i ++) {
			$param = $_SERVER['argv'][$i];
			$matches = [];
			if(preg_match('/^--([^\=]+)\=?(.*)$/', $param, $matches)) {
				$params[$matches[1]] = $matches[2];
			} else {
				$params[] = $param;
			}
		}
		
		if($method === 'run') {
			$id = array_shift(\Fwe::$names);
			$comp = $this->get($id);
			if($comp) {
                if (method_exists($comp, 'boot')) {
                    return \Fwe::invoke([$comp, 'boot'], $params);
                } else {
					trigger_error("组件ID为'$id'的类没有boot方法", E_USER_ERROR);
				}
			} else {
				trigger_error("不存在ID为'$id'的组件", E_USER_ERROR);
			}
		} else {
			return \Fwe::invoke([$this, $method], $params);
		}
	}
}
