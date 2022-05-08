<?php

namespace fwe\curl;

use fwe\base\TsVar;

class Boot {
	/**
	 * 启动的最大线程数
	 * 
	 * @var integer
	 */
	public $maxThreads = 2;

	/**
	 * send request
	 * 
	 * @var array|TsVar
	 */
	protected $_vars = [];
	
	/**
	 * recv response
	 * 
	 * @var TsVar
	 */
	protected $_var, $_stat;

	/**
	 * @var array
	 */
	protected $_calls = [];
	
	public function init() {
		$this->_stat = new TsVar('curl:stat');
		for($i=0; $i<$this->maxThreads; $i++) {
			$this->_vars[$i] = new TsVar("__curl{$i}__", 0, null, true);
		}
		
		$this->_var = new TsVar('curl:' . THREAD_TASK_NAME, 0, null, true);
		if(\Fwe::$app->id === THREAD_TASK_NAME) {
			$name = \Fwe::$name;
			\Fwe::$config->set('__app__', $name);
			foreach(glob(\Fwe::getAlias("@app/runtime/curl-$name-*.log")) as $filename) @unlink($filename);
		}

		\Fwe::$config->getOrSet(__CLASS__, function() {
			$fmt = 'curl:%0' . strlen((string) $this->maxThreads) . 'd';
			for($i=0; $i<$this->maxThreads; $i++) {
				$this->_stat[$i] = 0;
				create_task(sprintf($fmt, $i), INFILE, [$i]);
			}
			return true;
		});

		$this->_var->bindReadEvent(function(int $len, string $buf) {
			for($j=0; $j<$len; $j++) {
				\Fwe::$app->stat('curl:act', -1);
				\Fwe::$app->stat('curl:res');
				
				$key = null;
				$res = $this->_var->shift(true, $key);
				
				if(isset($this->_calls[$key])) {
					list($req, $call, $i) = $this->_calls[$key];
					unset($this->_calls[$key]);
					$this->_stat->inc($i, -1);
					
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
					
					\Fwe::$app->events--;
				}
			}
		});
	}
	
	public function make(IRequest $req, callable $call) {
		\Fwe::$app->events++;
		
		\Fwe::$app->stat('curl:act');
		$key = \Fwe::$app->stat('curl:req') - 1;
		$i = null;
		$this->_stat->minmax($i);
		$this->_stat->inc($i, 1);
		$var = $this->_vars[$i];

		$req->key = $this->_var->getKey();
		$req->resKey = $key;
		$var[$key] = $req;
		$this->_calls[$key] = [$req, $call, $i];
		
		$var->write();
	}
	
	public function cancel($key) {
		if(!isset($this->_calls[$key])) {
			return;
		}
	
		\Fwe::$app->events--;
		
		$i = $this->_calls[$key][2];
		unset($this->_calls[$key]);
		$this->_stat->inc($i, -1);
		
		\Fwe::$app->stat('curl:act', -1);
		\Fwe::$app->stat('curl:res');
		
		$this->_vars[$i][$key] = false;
	}
}
