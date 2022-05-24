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
	 * @var resource|\CurlMultiHandle
	 */
	protected $_mh;
	
	public function boot() {
		if(is_main_task()) return;
		
		$this->_taskIndex = (int) array_shift(\Fwe::$names);
		$this->_var = new TsVar("__curl{$this->_taskIndex}__", 0, null, true);
		$this->_var->bindReadEvent(function(int $len, string $buf) {
			for($i=0; $i<$len; $i++) {
				$this->read();
			}
		});
		$this->signalEvent();
		$this->_mh = curl_multi_init();

		$this->loop();

		\Fwe::$base->exit();
	}
	
	protected $_count = 0;
	protected function loop() {
		while($this->_running) {
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
								$key = curl_getinfo($ret['handle'], CURLINFO_PRIVATE);
								$val = $this->_reqs[$key];

								if($ret['result'] === CURLE_OK) {
									$val['res']->setError(0, 'OK');
								} else {
									$err = curl_strerror($ret['result']);
									$val['res']->setError($ret['result'], $err);
								}

								curl_multi_remove_handle($this->_mh, $val['ch']);
								curl_close($val['ch']);
								$this->write($val['var'], $key, $val['res']);
								$this->_count--;
							}
							unset($ret, $key, $val);
						} while($msgs);
						if(!$active && $this->_count) $this->write_all(-2, 'curl_multi_info_read');
						break;
					default:
						$err = curl_multi_strerror($ret);
						$this->write_all($ret, $err);
						break;
				}
			}

			\Fwe::$base->loop($this->_count ? \EventBase::LOOP_NONBLOCK : \EventBase::LOOP_ONCE);
			if($this->_count) {
				curl_multi_select($this->_mh, 0.001);
			}
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
				unset($this->_reqs[$key]);
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

		if(!($res instanceof IResponse)) {
			curl_close($ch);
			\Fwe::$app->error(sprintf("class %s is not instanceof %s", get_class($res), IResponse::class), 'curl');
			$this->write($var, $key, $res);
			return;
		}

		if($this->verbose) {
			$name =\Fwe::$config->get('__app__');
			curl_setopt($ch, CURLOPT_STDERR, fopen(\Fwe::getAlias("@app/runtime/curl-$name-$key.log"), 'ab'));
		}

		curl_setopt($ch, CURLOPT_PRIVATE, $key);

		$ret = curl_multi_add_handle($this->_mh, $ch);
		if($ret != CURLM_OK) {
			$err = curl_multi_strerror($ret);

			curl_close($ch);
			$res->setError($ret, $err);
			$this->write($var, $key, $res);
		} else {
			$this->_count ++;
			$this->_reqs[$key] = compact('req', 'var', 'ch', 'res');
		}
	}
	
	protected function write($var, $key, $res) {
		unset($this->_reqs[$key]);

		$var[$key] = $res;
		
		$var->write();
	}
}
