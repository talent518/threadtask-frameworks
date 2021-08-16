<?php

namespace fwe\event;

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
	
	/**
	 * @var TsVar
	 */
	protected $_statVar, $_wsVar;
	
	protected $_running = true;
	protected $_exitSig = 0;
	
	protected $_statEvent;
	public function init() {
		parent::init();
		
		$this->_statVar = new TsVar('__stat__');
		$this->_wsVar = new TsVar('__ws__', 0, null, true);
		
		$handler = [$this, 'signal'];
		
		pcntl_async_signals(true);
		pcntl_signal(SIGTERM, $handler, false);
		pcntl_signal(SIGINT, $handler, false);
		pcntl_signal(SIGUSR1, $handler, false);
		pcntl_signal(SIGUSR2, $handler, false);
		pcntl_signal(SIGALRM, [$this, 'signal_timeout'], false);
		
		pthread_sigmask(SIG_SETMASK, []);
		
		$this->_sigEvent = new \Event(\Fwe::$base, -1, \Event::TIMEOUT | \Event::PERSIST, function() {
			trigger_timeout();
			
			if(!$this->_running) {
				\Fwe::$base->exit();
				if(!defined('THREAD_TASK_NAME')) {
					$sig = $this->_exitSig?:SIGINT;
					task_wait($sig);
					@socket_shutdown($this->_sock);
					@socket_close($this->_sock);
					echo "Stopped: $sig\n";
				}
			}
		});
		$this->_sigEvent->addTimer(0.01);
	}
	
	public function signal(int $sig) {
		$this->_exitSig = $sig;
		$this->_running = false;
		
		if(!defined('THREAD_TASK_NAME'))
			task_set_run(false);
	}
	
	public function signal_timeout(int $sig) {
		throw new \Exception('Execute timeout');
	}
	
	public function strerror(string $msg, bool $isExit = true) {
		$err = socket_last_error();
		socket_clear_error();
		if($err === SOCKET_EINTR) return;
		
		ob_start();
		ob_implicit_flush(false);
		debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
		$trace = ob_get_clean();
		printf("[%s] %s(%d): %s\n%s", defined('THREAD_TASK_NAME') ? THREAD_TASK_NAME : 'main', $msg, $err, socket_strerror($err), $trace);
		
		if($isExit) exit; else return true;
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
	 * @var integer
	 */
	public $maxThreads = 4, $backlog = 128;
	
	protected $_sigEvent;
	public function listen() {
		($this->_sock = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) or $this->strerror('socket_create');
		@socket_set_option($this->_sock, SOL_SOCKET, SO_REUSEADDR, 1) or $this->strerror('socket_set_option', false);
		@socket_bind($this->_sock, $this->host, (int) $this->port) or $this->strerror('socket_bind');
		@socket_listen($this->_sock, $this->backlog) or $this->strerror('socket_listen');
		
		$this->_fd = socket_export_fd($this->_sock);
		
		$statFile = \Fwe::getAlias('@app/runtime/stat.log');
		
		$n = $ns = $ne = 0;
		$this->_statEvent = new \Event(\Fwe::$base, -1, \Event::TIMEOUT | \Event::PERSIST, function() use(&$statFile, &$n, &$ns, &$ne) {
			$n2 = $this->_statVar['conns'];
			$n3 = $this->_wsVar->count();
			$n4 = $this->_statVar['success'];
			$n5 = $this->_statVar['error'];
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
		
		$this->req($this->_fd, $this->maxThreads);
		for($i = 0; $i < $this->maxThreads; $i++) {
			$this->_statVar[$i] = 0;
			create_task(\Fwe::$name . ":req:$i", INFILE, [$this->_fd, $i]);
		}
		
		echo "Listened on {$this->host}:{$this->port}\n";
		
		return true;
	}
	
	public function stat($key, $inc = 1) {
		return $this->_statVar->inc($key, $inc);
	}
	
	protected $_httpEvent;
	protected $_httpIndex;
	public function req(int $fd, int $index) {
		$this->_httpIndex = $index;
		if($index != $this->maxThreads) $this->_sock = socket_import_fd($fd);
		
		$this->_httpEvent = new \EventHttp(\Fwe::$base);
		$this->_httpEvent->accept($this->_sock);
		$this->_httpEvent->setDefaultCallback(function(\EventHttpRequest $req) {
			$this->stat('conns');
			$headers = $req->getInputHeaders();
			
			echo "=========================\n";
			echo "Command: ", $req->getCommand(), PHP_EOL;
			echo "Host: ", $req->getHost(), PHP_EOL;
			echo "URI: ", $req->getUri(), PHP_EOL;
			echo "Headers: ", var_export($headers, true), PHP_EOL;

			$input = $req->getInputBuffer();
			$size = $headers['Content-Length'] ?? 0;
			if($input && $size > 0) {
				echo "Body:\n";
				while($size > 0 && ($buf = $input->read(min($size, 8192))) !== false) {
					$size -= strlen($buf);
					echo $buf;
				}
			}
			echo "\n";
			$req->sendReply(200, "OK");
			$this->stat('success');
		});
	}
	
	public function isWeb() {
		return true;
	}
	
	public function boot() {
		$method = array_shift(\Fwe::$names) ?: 'listen';
		
		$params = [];
		for($i = 1; $i < $_SERVER['argc']; $i ++) {
			$param = $_SERVER['argv'][$i];
			if(preg_match('/^--([^\=]+)\=?(.*)$/', $param, $matches)) {
				$params[$matches[1]] = $matches[2];
			} else {
				$params[] = $param;
			}
		}
		
		if($method === 'run') $method = $params[0];
		
		return \Fwe::invoke([$this, $method], $params);
	}
	
	public function __destruct() {
		if($this->_httpIndex != $this->maxThreads) {
			socket_export_fd($this->_sock, true);
			$this->_sock = null;
		}
	}
}

