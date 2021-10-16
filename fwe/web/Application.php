<?php
namespace fwe\web;

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

	public function init() {
		parent::init();
		
		$this->signalEvent();
	}

	public function signalHandler(int $sig) {
		parent::signalHandler($sig);

		$isExit = !defined('THREAD_TASK_NAME') || (strpos(THREAD_TASK_NAME, ':req:') !== false && $this->isEmptyReq());
		if(!$this->_running && ($isExit || strpos(THREAD_TASK_NAME, ':ws:') !== false)) {
			\Fwe::$base->exit();
			if(!defined('THREAD_TASK_NAME')) {
				task_wait($this->_exitSig);
				
				if($this->_sock) {
					@socket_shutdown($this->_sock, 2);
					@socket_close($this->_sock);
					$this->_sock = null;
				}
			}
		}
	}
	
	public $maxIdleSeconds = 3.0;
	public $maxRunSeconds = 30.0;
	public $timeoutStatus = 408;
	public $timeoutType = 'text/plain';
	public $timeoutData = 'Request Time-out';
	protected function isEmptyReq() {
		$count = 0;
		
		$time = microtime(true);
		foreach($this->_reqEvents as $reqEvent) { /* @var $reqEvent RequestEvent */
			if($reqEvent->isHTTP || $reqEvent->time + $this->maxIdleSeconds > $time) {
				if($reqEvent->runTime === null || $reqEvent->runTime + $this->maxRunSeconds > $time) {
					$count++;
				} else {
					$response = $reqEvent->getResponse();
					$response->setStatus($this->timeoutStatus);
					$response->setContentType($this->timeoutType);
					$response->end($this->timeoutData);
				}
			} else {
				$reqEvent->free();
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
	
	protected $_statEvent, $_lstEvent;

	public function listen() {
		($this->_sock = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) or $this->strerror('socket_create');
		@socket_set_option($this->_sock, SOL_SOCKET, SO_REUSEADDR, 1) or $this->strerror('socket_set_option', false);
		@socket_bind($this->_sock, $this->host, (int) $this->port) or $this->strerror('socket_bind');
		@socket_listen($this->_sock, $this->backlog) or $this->strerror('socket_listen');
		
		$this->_fd = socket_export_fd($this->_sock);
		
		$statFile = \Fwe::getAlias('@app/runtime/stat.log');
		
		$n = $ns = $ne = 0;
		$this->_statEvent = new \Event(\Fwe::$base, -1, \Event::TIMEOUT | \Event::PERSIST, function() use(&$statFile, &$n, &$ns, &$ne) {
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
			file_put_contents($statFile, "[$time] $n connects, $n3 accepts, $ns successes, $ne errors\n", FILE_APPEND);
			$n = $n2;
			$ns = $n4;
			$ne = $n5;
		});
		$this->_statEvent->addTimer(1);
		
		$this->_lstEvent = new \Event(\Fwe::$base, $this->_fd, \Event::READ | \Event::PERSIST, function() {
			$addr = $port = null;
			$fd = @socket_accept_ex($this->_fd, $addr, $port);
			if(!$fd) return;

			@socket_set_option($fd, SOL_SOCKET, SO_LINGER, ['l_onoff'=>1, 'l_linger'=>1]) or $this->strerror('socket_set_option', false);
			$fd = socket_export_fd($fd, true);
			
			$i = $this->stat('conns') - 1;
			$reqVar = $this->_reqVars[$i % $this->maxThreads]; /* @var $reqVar TsVar */
			$reqVar[$i] = [$fd, $addr, $port];

			$reqVar->write();
		});
		$this->_lstEvent->add();
		
		for($i = 0; $i < $this->maxWsGroups; $i++) {
			$this->_wsVars[$i] = new TsVar("__ws{$i}__", 0, null, true);
			create_task(\Fwe::$name . ":ws:$i", INFILE, [$i]);
		}

		for($i = 0; $i < $this->maxThreads; $i++) {
			$this->_reqVars[$i] = new TsVar("req:$i", 0, null, true);
			create_task(\Fwe::$name . ":req:$i", INFILE, [$i]);
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
			if(!$this->_wsVar->read()) return;
			
			$key = null;
			list($fd, $addr, $port) = $this->_wsVar->shift(true, $key);

			$this->_reqEvents[$key] = \Fwe::createObject(WsEvent::class, compact('fd', 'addr', 'port', 'key'));
		});
		$this->_reqEvent->add();
		
		$ping = pack('CC', 0x8a, 4) . 'ping';
		$this->_statEvent = new \Event(\Fwe::$base, -1, \Event::TIMEOUT | \Event::PERSIST, function() use($ping) {
			$this->sendWs($ping);
		});
		$this->_statEvent->addTimer($this->wsPingDelay);
	}
	
	public function addWs(int $index, $key, array $args) {
		if($index >= $this->maxWsGroups) $index = $this->maxWsGroups - 1;
		elseif($index < 0) $index = 0;

		$wsVar = $this->_wsVars[$index];
		$wsVar[$key] = $args;
		$wsVar->write();
	}
	
	public function sendWs($data) {
		foreach($this->_reqEvents as $ev) {
			$ev->send($data);
		}
	}
	
	public function setReqEvent(int $key, $val = false) {
		if($val) $this->_reqEvents[$key] = $val;
		else unset($this->_reqEvents[$key]);
	}

	/**
	 * @var array|TsVar
	 */
	protected $_wsVars = [];

	public $keepAlive = 10;
	public function req(int $index) {
		$this->_reqIndex = $index;
		$this->_reqVars = new TsVar("req:$index", 0, null, true);

		for($i = 0; $i < $this->maxWsGroups; $i++) {
			$this->_wsVars[$i] = new TsVar("__ws{$i}__", 0, null, true);
		}
		
		$this->_reqEvent = $this->_reqVars->newReadEvent(function() use($index) {
			if(!$this->_reqVars->read()) return;
			
			$key = null;
			list($fd, $addr, $port) = $this->_reqVars->shift(true, $key);
			
			$keepAlive = microtime(true) + $this->keepAlive;
			$this->_reqEvents[$key] = \Fwe::createObject(RequestEvent::class, compact('fd', 'addr', 'port', 'key', 'keepAlive'));
		});
		$this->_reqEvent->add();
	}
	
	public $statics = ['/' => '@app/static/'];
	public function getAction(string $route, array &$params) {
		foreach($this->statics as $prefix => $path) {
			$n = strlen($prefix);
			if(!strncmp($route, $prefix, $n) && is_file($file = \Fwe::getAlias($path . substr($route, $n)))) {
				return \Fwe::createObject(StaticAction::class, compact('route', 'prefix', 'path', 'file', 'params'));
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
		
		if($method === 'run') $method = $params[0];
		
		return \Fwe::invoke([$this, $method], $params);
	}
}
