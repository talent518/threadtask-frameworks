<?php
namespace fwe\web;

use fwe\base\Action;
use fwe\base\TsVar;
use fwe\db\IPool;
use fwe\utils\FileHelper;

/**
 * @property array $cleanPools
 */
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

	public $gcTimes = 20; // 120;
	public $poolExpire = 5; // 60;

	protected $_isReq = false;
	protected $_cleanPools = ['redis', 'db'];

	public function init() {
		parent::init();
		
		$i = 0;
		$memsize = 0;
		$this->signalEvent(function() use(&$i, &$memsize) {
			if($this->_isReq && $this->isEmptyReq() && !$this->_running) {
				\Fwe::$base->exit();
			}
			if(++$i > $this->gcTimes) {
				$i = 0;
				$time = microtime(true) - $this->poolExpire;
				$pools = [];
				foreach($this->_cleanPools as $id) { /* @var $pool IPool */
					if(($pool = $this->get($id, false)) !== null) {
						$n = $pool->clean($time);
						$pools[] = "$id: [$n]";
					}
				}
				unset($pool);
				$pools = implode(', ', $pools);
				
				gc_collect_cycles();
				$size = memory_get_usage() - $memsize;
				$memsize += $size;
				$this->debug("pools: {{$pools}}, Memory: $size", 'memory');
			}
		});
	}
	
	public function getCleanPools() {
		return $this->_cleanPools;
	}
	
	public function setCleanPools($pools) {
		foreach((array) $pools as $pool) {
			$this->_cleanPools[] = $pool;
		}
		$this->_cleanPools = array_unique($this->_cleanPools, SORT_REGULAR);
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
			if(!$this->_running && !$reqEvent->isHTTP) {
				$reqEvent->free();
			} elseif(($reqEvent->runTime && $reqEvent->runTime + $this->maxRunSeconds < $time) ||
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
	
	public function strerror(string $msg, bool $isExit = true) {
		$errno = socket_last_error();
		$error = socket_strerror($errno);
		socket_clear_error();
		if($errno === SOCKET_EINTR) return;
		
		if($isExit) {
			exit("$msg: $error\n");
		} else {
			echo "$msg: $error\n";
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
	public $maxThreads = 4, $backlog = 128, $maxWsGroups = 2, $maxAccepts = 1;
	public $isToFile = true;
	
	/**
	 * @var TsVar
	 */
	protected $_connStatVar, $_curlStatVar;

	/**
	 * @var \Event
	 */
	protected $_statEvent, $_lstEvent, $_watchEvent, $_watchTimeEvent;

	/**
	 * @var array
	 */
	public $watchs = [];
	
	/**
	 * @var boolean
	 */
	public $isWatch = true;
	
	/**
	 * @var float
	 */
	public $watchDelay = 3.0, $watchTimeout = 0.1;
	
	/**
	 * @var float
	 */
	protected $_watchTime;
	
	public function listen() {
		($this->_sock = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) or $this->strerror('socket_create');
		@socket_set_option($this->_sock, SOL_SOCKET, SO_REUSEADDR, 1) or $this->strerror('socket_set_option', false);
		if($this->maxAccepts > 1) {
			@socket_set_nonblock($this->_sock) or $this->strerror('socket_set_nonblock');
		}
		@socket_bind($this->_sock, $this->host, (int) $this->port) or $this->strerror('socket_bind');
		@socket_listen($this->_sock, $this->backlog) or $this->strerror('socket_listen');
		
		$this->_fd = socket_export_fd($this->_sock);
		$this->_connStatVar = new TsVar("conn:stat");
		$this->_curlStatVar = new TsVar("curl:stat");
		
		$statFp = fopen(\Fwe::getAlias('@app/runtime/stat.log'), 'a');
		$connFp = fopen(\Fwe::getAlias('@app/runtime/conn.log'), 'a');
		$curlFp = fopen(\Fwe::getAlias('@app/runtime/curl.log'), 'a');
		
		$n = $ns = $ne = $ncs = $ncg = 0;
		$this->_statEvent = new \Event(\Fwe::$base, -1, \Event::TIMEOUT | \Event::PERSIST, function() use(&$statFp, &$connFp, &$curlFp, &$n, &$ns, &$ne, &$ncs, &$ncg) {
			$n2 = $this->stat('conns', 0);
			$n3 = 0;
			for($i = 0; $i < $this->maxWsGroups; $i++) {
				$n3 += $this->_wsVars[$i]->count();
			}
			$n4 = $this->stat('success', 0);
			$n5 = $this->stat('error', 0);
			$n6 = $this->stat('cache:set', 0);
			$n7 = $this->stat('cache:get', 0);
			$n = $n2 - $n;
			$ns = $n4 - $ns;
			$ne = $n5 - $ne;
			$ncs = $n6 - $ncs;
			$ncg = $n7 - $ncg;
			$time = date('Y-m-d H:i:s');
			$rc = $this->stat('realConns', 0);
			fwrite($statFp, "[$time] $rc current connects, $n connects/second, $n3 accepts, $ns successes, $ne errors, cache $ncs sets / $ncg gets\n");
			$conns = implode(' ', $this->_connStatVar->all());
			fwrite($connFp, "$conns\n");
			$curls = implode(' ', $this->_curlStatVar->all());
			fwrite($curlFp, "$curls\n");
			$n = $n2;
			$ns = $n4;
			$ne = $n5;
			$ncs = $n6;
			$ncg = $n7;
		});
		$this->_statEvent->addTimer(1);
		
		for($i = 0; $i < $this->maxWsGroups; $i++) {
			$this->_wsVars[$i] = new TsVar("__ws{$i}__", 0, null, true);
		}

		for($i = 0; $i < $this->maxThreads; $i++) {
			$this->_reqVars[$i] = new TsVar("req:$i", 0, null, true);
			$this->_connStatVar[$i] = 0;
		}
		
		$this->logInit();
		
		echo "Listened on {$this->host}:{$this->port}\n";
		
		if($this->maxAccepts > 1) {
			for($i=0; $i < $this->maxAccepts; $i++) {
				create_task(\Fwe::$name . ':accept:' . $i, INFILE, [$i, $this->_fd]);
			}
		} else {
			create_task(\Fwe::$name . ':accept', INFILE, [0, $this->_fd]);
		}
		
		if($this->isWatch && extension_loaded('inotify')) {
			$fd = inotify_init();
			$watchs = $this->watchs;
			$watchs['@app'] = ['views', 'static', 'runtime'];
			$watchs['@fwe'] = ['views'];
			foreach($watchs as $path => $filters) {
				if(is_int($path)) {
					$path = $filters;
					$filters = [];
				}
				$this->addWatch($fd, \Fwe::getAlias($path), $filters);
			}
			$this->_watchEvent = new \Event(\Fwe::$base, $fd, \Event::READ | \Event::PERSIST, function() use($fd) {
				$rootDir = ROOT . '/';
				$rootLen = strlen($rootDir);
				$events = inotify_read($fd);
				foreach($events as $event) {
					if(pathinfo($event['name'], PATHINFO_EXTENSION) !== 'php') continue;
					
					$path = $this->wdPaths[$event['wd']] ?? '';
					if(!strncmp($path, $rootDir, $rootLen)) {
						$path = substr($path, $rootLen);
					}
					$path .=  '/' . $event['name'];
					
					switch($event['mask']) {
						case IN_CREATE:
							$name = 'CREATE';
							break;
						case IN_MODIFY:
							$name = 'MODIFY';
							break;
						case IN_DELETE:
							$name = 'DELETE';
							break;
						default:
							$name = 'Unknown';
							break;
					}
					
					$this->info("$name $path", 'watch');
					$this->_watchTime = microtime(true);
				}
			});
			$this->_watchEvent->add();
			$this->_watchTimeEvent = new \Event(\Fwe::$base, -1, \Event::TIMEOUT | \Event::PERSIST, function() {
				if($this->_watchTime !== null && microtime(true) - $this->_watchTime >= $this->watchDelay) {
					$this->signalHandler(SIGUSR1);
				}
			});
			$this->_watchTimeEvent->addTimer($this->watchTimeout);
		}
		
		return true;
	}
	
	protected $wdPaths = [];
	protected function addWatch($fd, string $path, array $filters = []) {
		$wd = inotify_add_watch($fd, $path, IN_CREATE | IN_MODIFY | IN_DELETE);
		if($wd) {
			$this->wdPaths[$wd] = $path;
			
			foreach(FileHelper::list($path) as $file) {
				if(in_array($file, $filters)) continue;
				
				$fileName = "$path/$file";
				if(is_dir($fileName)) {
					$this->addWatch($fd, $fileName, $filters);
				}
			}
		}
	}
	
	protected $_index;
	public function accept(int $index, int $fd) {
		$this->_index = $index;
		$this->_fd = $fd;
		$this->_connStatVar = new TsVar("conn:stat");
		for($i = 0; $i < $this->maxThreads; $i++) {
			$this->_reqVars[$i] = new TsVar("req:$i", 0, null, true);
		}
		
		$fmt = '%s:req:%0' . strlen((string) $this->maxThreads) . 'd';
		$reqTasks = [];
		$this->_lstEvent = new \Event(\Fwe::$base, $this->_fd, \Event::READ | \Event::PERSIST, function() use(&$reqTasks, $fmt) {
			$addr = $port = null;
			$fd = socket_accept_ex($this->_fd, $addr, $port);
			if(!$fd) {
				// printf("accept: %d\n", $this->_index);
				return;
			}
			
			@socket_set_option($fd, SOL_SOCKET, SO_LINGER, ['l_onoff'=>1, 'l_linger'=>1]) or $this->strerror('socket_set_option', false);
			$addr2 = $port2 = null;
			if(!socket_getsockname($fd, $addr2, $port2)) {
				$addr2 = $this->host;
				$port2 = $this->port;
			}
			
			$fd = socket_export_fd($fd, true);
			
			$this->stat('realConns');
			$key = $this->stat('conns');
			
			$i = null;
			$this->_connStatVar->minmax($i);
			$this->_connStatVar->inc($i, 1);
			$reqVar = $this->_reqVars[$i]; /* @var $reqVar TsVar */
			$reqVar->set($key, [$fd, $addr, $port, $addr2, $port2]);
			$reqVar->write();
			
			if(empty($reqTasks[$i])) {
				$reqTasks[$i] = 1;
				$name = sprintf($fmt, \Fwe::$name, $i);
				\Fwe::$config->getOrSet($name, function() use($name, $i) {
					return create_task($name, INFILE, [$i]);
				});
			}
		});
		$this->_lstEvent->add();
	}
	
	protected $_reqIndex = 0, $_reqEvents = [];

	/**
	 * @var TsVar
	 */
	protected $_wsVar;
	
	public $wsPingDelay = 30;
	public function ws(int $index) {
		$this->_reqIndex = $index;
		$this->_wsVar = new TsVar("__ws{$index}__", 0, null, true);
		$this->_wsVar->bindReadEvent(function(int $len, string $buf) {
			for($i=0; $i<$len; $i++) {
				$key = null;
				list($fd, $addr, $port, $doClass) = $this->_wsVar->shift(true, $key);

				$this->_reqEvents[$key] = \Fwe::createObject(WsEvent::class, compact('fd', 'addr', 'port', 'key', 'doClass'));
			}
		});
		
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
			$n = strlen((string)$this->maxWsGroups);
			$name = sprintf("%s:ws:%0{$n}d", \Fwe::$name, $index);
			\Fwe::$config->getOrSet($name, function() use($name, $index) {
				return create_task($name, INFILE, [$index]);
			});
		}

		$wsVar = $this->_wsVars[$index];
		$wsVar->set($key, $args);
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
		
		$this->_reqVars->bindReadEvent(function(int $len, string $buf) use($index) {
			for($i=0; $i<$len; $i++) {
				$key = null;
				list($fd, $addr, $port, $addr2, $port2) = $this->_reqVars->shift(true, $key);
				
				$keepAlive = microtime(true) + $this->keepAlive;
				$this->_reqEvents[$key] = \Fwe::createObject(RequestEvent::class, compact('fd', 'addr', 'port', 'addr2', 'port2', 'key', 'keepAlive'));
			}
		});
	}
	
	public $maxBodyLen = 8*1024*1024;
	public function beforeAction(Action $action, array &$params = []): bool {
		if($action->controller instanceof StaticController) {
			return true;
		} else {
			$request = $params['request'];
			if($request->bodylen > $this->maxBodyLen) {
				$request->getResponse()->setStatus(403)->end('<h1>Request Entity Too Large</h1>');
				return false;
			} else {
				return true;
			}
		}
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
