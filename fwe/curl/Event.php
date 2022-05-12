<?php
namespace fwe\curl;

use fwe\base\Exception;
use fwe\base\TsVar;

class Event {

	/**
	 * @var integer
	 */
	public $timeout = 30;

	/**
	 * @var boolean
	 */
	public $verbose = false;

	/**
	 * @var resource|\CurlMultiHandle
	 */
	protected $_mh;

	/**
	 * recv response
	 * 
	 * @var TsVar
	 */
	protected $_stat;
	
	protected $_event, $_events = [], $_timeEvent;
	protected $_reqs = [];
	
	public function init() {
		$this->_stat = new TsVar('curl:stat');
		$this->_stat[THREAD_TASK_NAME] = 0;
		$this->_mh = curl_multi_init();
		$this->_event = curl_multi_socket_event($this->_mh, [$this, 'bindEvent'], microtime(true));
		
	}
	
	public function __destruct() {
		\Fwe::debug(get_called_class(), null, true);
		
		curl_multi_close($this->_mh);
		$this->_event = null;
		$this->_reqs = null;
		if($this->_timeEvent) {
			$this->_timeEvent->free();
			$this->_timeEvent = null;
		}
		foreach($this->_events as $evt) {
			$evt->free();
		}
		$this->_events = null;
	}
	
	public function bindEvent($ch, int $fd, int $what, float $time) {
		$key = ($ch ? curl_getinfo($ch, CURLINFO_PRIVATE) : -1);
		// $url = ($key >= 0 && isset($this->_reqs[$key]) ? $this->_reqs[$key]['req']->url : '#null');
		// echo "[bindEvent] key: $key, fd: $fd, what: $what, url: $url\n";
		switch($what) {
			case CURL_POLL_REMOVE:
				if(isset($this->_events[$fd])) {
					$this->_events[$fd]->free();
					unset($this->_events[$fd]);
				}
				break;
			case CURL_POLL_IN:
			case CURL_POLL_OUT:
			case CURL_POLL_INOUT:
				switch($what) {
					case CURL_POLL_IN:
						$what = \Event::READ;
						break;
					case CURL_POLL_OUT:
						$what = \Event::WRITE;
						break;
					default:
						$what = \Event::READ | \Event::WRITE;
						break;
				}
				
				if(isset($this->_events[$fd])) {
					$event = $this->_events[$fd];
					if($event->pending !== $what) {
						$event->free();
					} else {
						break;
					}
				}
				
				$event = new \Event(\Fwe::$base, $fd, $what | \Event::PERSIST, [$this, 'eventHandler']);
				$event->add();
				$this->_events[$fd] = $event;
				break;
			case CURL_SOCKET_TIMEOUT:
				if($fd >= 0) {
					if(!$this->_timeEvent) {
						echo "timeout: $key $fd\n";
						$this->_timeEvent = new \Event(\Fwe::$base, -1, \Event::TIMEOUT, [$this, 'timeoutHandler']);
						$this->_timeEvent->add($fd / 1000);
					}
				} elseif($this->_timeEvent) {
					$this->_timeEvent->free();
					$this->_timeEvent = null;
				}
				break;
			default:
				break;
		}
	}
	
	protected function readInfo(int $fd, int $what) {
		$runnings = 0;
		$ret = curl_multi_socket_action($this->_mh, $fd, $what, $runnings);
		
		// echo "[ readInfo] fd: $fd, what: $what, ret: $ret, runnings: $runnings\n";
		do {
			$msgs = 0;
			$ret = curl_multi_info_read($this->_mh, $msgs);
			if($ret) {
				$key = curl_getinfo($ret['handle'], CURLINFO_PRIVATE);
				$val = $this->_reqs[$key];
				unset($this->_reqs[$key]);

				if($val) {
					if($ret['result'] === CURLE_OK) {
						$val['res']->setError(0, 'OK');
					} else {
						$err = curl_strerror($ret['result']);
						$val['res']->setError($ret['result'], $err);
					}

					$this->call($val['req'], $val['res'], $val['call'], $val['ch']);
				}
			}
			unset($ret, $key, $val);
		} while($msgs);
		
		if($runnings <= 0) {
			// $this->bindEvent(null, 0, CURL_SOCKET_TIMEOUT, microtime(true));
			if($this->_timeEvent) $this->_timeEvent->free();
			$this->_timeEvent = new \Event(\Fwe::$base, -1, \Event::TIMEOUT, [$this, 'timeoutHandler']);
			$this->_timeEvent->add(0);
		}
	}
	
	public function timeoutHandler() {
		if($this->_timeEvent) {
			$this->_timeEvent->free();
			$this->_timeEvent = null;
		}
		$this->readInfo(CURL_SOCKET_TIMEOUT, 0);
	}
	
	public function eventHandler($fd, $what, $arg) {
		$what = (($what & \Event::READ) ? CURL_CSELECT_IN : 0) | (($what & \Event::WRITE) ? CURL_CSELECT_OUT : 0);
		
		$this->readInfo($fd, $what);
	}

	public function make(IRequest $req, callable $call) {
		// $t = microtime(true);
		$req->setOption(CURLOPT_TIMEOUT, $this->timeout);
		$req->setOption(CURLOPT_VERBOSE, $this->verbose);

		$res = null; /* @var $res \fwe\curl\IResponse */
		$ch = $req->make($res);

		if(!($res instanceof IResponse)) {
			curl_close($ch);
			throw new Exception(sprintf("class %s is not instanceof %s", get_class($res), IResponse::class));
			return;
		}
		
		$this->_reqs[] = compact('ch', 'req', 'res', 'call');
		$key = array_key_last($this->_reqs);

		$req->key = 'unuse ts var';
		$req->resKey = $key;

		if($this->verbose) {
			$name =\Fwe::$name;
			curl_setopt($ch, CURLOPT_STDERR, fopen(\Fwe::getAlias("@app/runtime/curl-$name-$key.log"), 'ab'));
		}

		curl_setopt($ch, CURLOPT_PRIVATE, $key);
		curl_setopt($ch, CURLOPT_LOW_SPEED_TIME, 3);
		curl_setopt($ch, CURLOPT_LOW_SPEED_LIMIT, 10);

		$ret = curl_multi_add_handle($this->_mh, $ch);
		if($ret != CURLM_OK) {
			$err = curl_multi_strerror($ret);

			$res->setError($ret, $err);

			unset($this->_reqs[$key]);
			$this->call($req, $res, $call, $ch);
		} else {
			\Fwe::$app->events++;
			$this->_stat->inc(THREAD_TASK_NAME, 1);
		}

		// printf("%.6f\n", microtime(true) - $t);
		
		/*if(!$this->_timeEvent) {
			echo "timeout: $key 0\n";
			$this->_timeEvent = new \Event(\Fwe::$base, -1, \Event::TIMEOUT, [$this, 'timeoutHandler']);
			$this->_timeEvent->add(0);
		}*/
	}
	
	protected function call(IRequest $req, IResponse $res, callable $call, $ch) {
		\Fwe::$app->events--;
		$this->_stat->inc(THREAD_TASK_NAME, -1);

		curl_multi_remove_handle($this->_mh, $ch);
		curl_close($ch);

		try {
			$res->completed();
		} catch(\Throwable $ex) {
			\Fwe::$app->error($ex, 'curl');
		}
		
		try {
			$call($res, $req);
		} catch(\Throwable $ex) {
			\Fwe::$app->error($ex, 'curl');
		}
	}
	
	public function cancel($key) {
		if(!isset($this->_reqs[$key])) {
			return;
		}
		
		\Fwe::$app->events--;
		$this->_stat->inc(THREAD_TASK_NAME, -1);
		
		$val = $this->_reqs[$key];
		unset($this->_reqs[$key]);
		
		curl_multi_remove_handle($this->_mh, $val['ch']);
		curl_close($val['ch']);
	}

}
