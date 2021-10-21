<?php
namespace fwe\curl;

use fwe\base\TsVar;

class Application extends \fwe\base\Application {

	/**
	 * @var integer
	 */
	public $timeout = 30;

	/**
	 * @var boolean
	 */
	public $verbose = false;

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
		
		$this->_mh = curl_multi_init();

		$this->loop();

		\Fwe::$base->exit();
	}
	
	protected $_count = 0;
	protected function loop() {
		if(!task_get_run()) return;

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

									if($ret['result'] === CURLE_OK) {
										$val['res']->setError(0, 'OK');
									} else {
										$err = curl_strerror($ret['result']);
										$val['res']->setError($ret['result'], $err);
									}
									
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
			$read = [$this->_var->getReadFd()];
			$write = $except = [];
			$ret = @socket_select($read, $write, $except, $this->_count ? 0 : 1, 100); // 100us
			if($ret === 0) continue;
			if($ret === false) break;
			
			if(($n = $this->_var->read(128)) === false) return;
			
			for($i=0; $i<$n; $i++) $this->read();
		}
		
		$this->write_all(-2, 'stopped curl');
	}
	
	protected function write_all(int $errno = 0, string $error = 'OK') {
		foreach($this->_reqs as $key => $val) {
			$val['res']->setError($errno, $error);
			curl_multi_remove_handle($this->_mh, $val['ch']);
			curl_close($val['ch']);
			$this->write($val['var'], $key, $val['res']);
		}
		
		$this->_count = 0;
		$this->_reqs = [];
	}

	protected $_reqs = [];
	public function read() {
		$key = null;
		/* @var $req \fwe\curl\IRequest */
		$req = $this->_var->shift(true, $key);
		if(!($req instanceof IRequest)) {
			if(isset($this->_reqs[$key])) {
				$val = $this->_reqs[$key];
				curl_multi_remove_handle($this->_mh, $val['ch']);
				curl_close($val['ch']);
				$this->_count--;
			}
			return;
		}
		if(!isset($this->_vars[$req->key])) {
			$this->_vars[$req->key] = new TsVar($req->key, 0, null, true);
		}
		$var = $this->_vars[$req->key];

		$req->setOption(CURLOPT_TIMEOUT, $this->timeout);
		$req->setOption(CURLOPT_VERBOSE, $this->verbose);

		$res = null; /* @var $res \fwe\curl\IResponse */
		$ch = $req->make($res);
		
		$ret = curl_multi_add_handle($this->_mh, $ch);
		if($ret != CURLM_OK) {
			$err = curl_multi_strerror($ret);
			$ex = \Exception($err, $ret);
			echo "$ex\n";

			curl_close($ch);
			$res->setError($ret, $err);
			$this->write($var, $key, $res);
			return;
		}
		
		$this->_count ++;
		$this->_reqs[$key] = compact('req', 'var', 'ch', 'res');
	}
	
	protected function write($var, $key, $res) {
		$var[$key] = $res;
		
		$var->write();
	}
}
