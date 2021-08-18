<?php
namespace fwe\curl;

use fwe\base\TsVar;

class Application extends \fwe\base\Application {

	/**
	 * @var TsVar
	 */
	protected $_var;
	
	/**
	 * @var array|TsVar
	 */
	protected $_vars = [];
	
	/**
	 * @var \Event
	 */
	protected $_event, $_sigEvent;
	
	/**
	 * @var integer
	 */
	protected $_taskIndex;
	
	/**
	 * @var resource
	 */
	protected $_mh;
	
	public function boot() {
		$this->_taskIndex = (int) array_shift(\Fwe::$names);
		$this->_var = new TsVar("__curl{$this->_taskIndex}__", 0, null, true);
		
		$handler = [$this, 'signal'];

		pcntl_async_signals(true);
		pcntl_signal(SIGTERM, $handler, false);
		pcntl_signal(SIGINT, $handler, false);
		pcntl_signal(SIGUSR1, $handler, false);
		pcntl_signal(SIGUSR2, $handler, false);
		pcntl_signal(SIGALRM, [$this, 'signal_timeout'], false);
		
		pthread_sigmask(SIG_SETMASK, []);
		
		$this->_mh = curl_multi_init();

		$this->loop();

		\Fwe::$base->exit();
	}
	
	protected $_exitSig = SIGINT;
	public function exitSig() {
		return $this->_exitSig;
	}
	
	protected $_running = true;
	public function signal(int $sig) {
		$this->_exitSig = $sig;
		$this->_running = false;
		
		if(!defined('THREAD_TASK_NAME'))
			task_set_run(false);
	}
	
	public function signal_timeout(int $sig) {
		throw new \Exception('Execute timeout');
	}
	
	protected $_count = 0;
	protected function loop() {
		$fd = $this->_var->getReadFd();

		while($this->_running || $this->_count) {
			if($this->_count) {
				$active = 0;
				$ret = curl_multi_exec($this->_mh, $active);
				switch($ret) {
					case CURLM_OK:
					case CURLM_CALL_MULTI_PERFORM:
						do {
							$msgs = 0;
							$ret = curl_multi_info_read($this->_mh, $msgs);
							if($ret) {
								foreach($this->_reqs as $key => $val) {
									if($val['ch'] != $ret['handle']) continue;
									
									curl_multi_remove_handle($this->_mh, $val['ch']);
									curl_close($val['ch']);
									$this->write($val['var'], $key, $val['res']);
									
									unset($this->_reqs[$key]);
									$this->_count--;
									break;
								}
							}
						} while($msgs);
						if(!$active && $this->_count) $this->write_all(-2, 'curl_multi_info_read');
						break;
					default:
						$err = curl_multi_strerror($ret);
						$this->write_all($ret, $err);
						break;
				}
			}
			$read = [$fd];
			$write = $except = [];
			$ret = @socket_select($read, $write, $except, $this->_count ? 0 : 1, 100); // 100us
			if($ret === 0) continue;
			if($ret === false) {
				$this->write_all(-1, 'socket_select is return false');
				continue;
			}
			
			$buf = @socket_read($fd, 128);
			if($buf === false || ($n = strlen($buf)) == 0) return;
			
			for($i=0; $i<$n; $i++) $this->read();
		}
	}
	
	protected function write_all(int $errno = 0, string $error = 'none') {
		foreach($this->_reqs as $key => $val) {
			if($errno !== 0 || $error !== 'none') {
				$val['res']->setStatus($errno, $error);
			}
			curl_multi_remove_handle($this->_mh, $val['ch']);
			curl_close($val['ch']);
			$this->write($val['var'], $key, $val['res']);
		}
		
		$this->_count = 0;
		$this->_reqs = [];
	}

	protected $_reqs = [];
	public function read() {
		/* @var $req \fwe\curl\Request */
		$req = $this->_var->shift(true, $key);
		if(!isset($this->_vars[$req->key])) {
			$this->_vars[$req->key] = new TsVar($req->key, 0, null, true);
		}
		$var = $this->_vars[$req->key];
		$ch = $req->make();
		
		$protocol = substr($req->url, strpos($req->url, '://'));

		/* @var $res \fwe\curl\Response */
		$res = \Fwe::createObject($req->responseClass, compact('protocol'));
		
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_HEADERFUNCTION, [$res, 'headerHandler']);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 0);
		curl_setopt($ch, CURLOPT_WRITEFUNCTION, [$res, 'writeHandler']);
		
		$ret = curl_multi_add_handle($this->_mh, $ch);
		if($ret != CURLM_OK) {
			$err = curl_multi_strerror($ret);
			$ex = \Exception($err, $ret);
			echo "$ex\n";

			curl_close($ch);
			$res->setStatus($ret, $err);
			$this->write($var, $key, $res);
			return;
		}
		
		$this->_count ++;
		$this->_reqs[$key] = compact('req', 'var', 'ch', 'protocol', 'res');
	}
	
	protected function write($var, $key, Response $res) {
		$var[$key] = $res;
		
		@socket_write($var->getWriteFd(), 'a', 1);
	}
}