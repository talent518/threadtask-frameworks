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
	 * @var \Event
	 */
	protected $_event;
	
	/**
	 * recv response
	 * 
	 * @var TsVar
	 */
	protected $_var, $_stat;

	public function init() {
		$this->_stat = new TsVar('curl:stat');
		for($i=0; $i<$this->maxThreads; $i++) {
			$this->_vars[$i] = new TsVar("__curl{$i}__", 0, null, true);
		}
		
		if(defined('THREAD_TASK_NAME')) {
			$this->_var = new TsVar('curl:' . THREAD_TASK_NAME, 0, null, true);
		} else {
			$this->_var = new TsVar('curl:main', 0, null, true);

			$name = \Fwe::$name;
			\Fwe::$config->set('__app__', $name);
			foreach(glob(\Fwe::getAlias("@app/runtime/curl-$name-*.log")) as $filename) @unlink($filename);
		}

		\Fwe::$config->getOrSet(__CLASS__, function() {
			for($i=0; $i<$this->maxThreads; $i++) {
				$this->_stat[$i] = 0;
				create_task("curl:$i", INFILE, [$i]);
			}
			return true;
		});

		$this->_event = $this->_var->newReadEvent([$this, 'read']);
	}
	
	protected $_events = 0;
	protected $_call = [];
	public function make(IRequest $req, callable $call) {
		if(!$this->_events++) $this->_event->add();
		
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
		$this->_call[$key] = [$req, $call, $i];
		
		$var->write();
	}
	
	public function read() {
		if(($n = $this->_var->read(128)) === false) return;

		for($j=0; $j<$n; $j++) {
			if(!--$this->_events) $this->_event->del();

			\Fwe::$app->events--;
			
			\Fwe::$app->stat('curl:act', -1);
			\Fwe::$app->stat('curl:res');

			$key = null;
			$res = $this->_var->shift(true, $key);

			if(!isset($this->_call[$key])) {
				return;
			}

			list($req, $call, $i) = $this->_call[$key];
			unset($this->_call[$key]);
			$this->_stat->inc($i, -1);
			
			try {
				$call($res, $req);
			} catch(\Throwable $ex) {
				echo "$ex\n";
			}
		}
	}
	
	public function cancel($key) {
		if(!isset($this->_call[$key])) {
			return;
		}
	
		if(!--$this->_events) $this->_event->del();
		
		$i = $this->_call[$key][2];
		unset($this->_call[$key]);
		$this->_stat->inc($i, -1);
		
		\Fwe::$app->events--;
		\Fwe::$app->stat('curl:act', -1);
		\Fwe::$app->stat('curl:res');
		
		$this->_vars[$i][$key] = false;
	}
}
