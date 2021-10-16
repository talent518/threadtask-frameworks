<?php

namespace fwe\curl;

use fwe\base\TsVar;

class Boot {
	/**
	 * 启动的最大线程数
	 * 
	 * @var integer
	 */
	public $maxThreads = 1;

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
	protected $_var;

	protected static $isCreate = true;
	public function init() {
		for($i=0; $i<$this->maxThreads; $i++) {
			$this->_vars[$i] = new TsVar("__curl{$i}__", 0, null, true);
		}
		
		if(defined('THREAD_TASK_NAME')) {
			$this->_var = new TsVar('curl:' . THREAD_TASK_NAME, 0, null, true);
		} else {
			$this->_var = new TsVar('curl:main', 0, null, true);

			if(static::$isCreate) {
				static::$isCreate = false;
				for($i=0; $i<$this->maxThreads; $i++) {
					create_task("curl:$i", INFILE, [$i]);
				}
			}
		}

		$this->_event = $this->_var->newReadEvent([$this, 'read']);
	}
	
	private $_events = 0;
	private $_call = [];
	public function make(Request $req, callable $call) {
		if(!$this->_events++) $this->_event->add();
		
		\Fwe::$app->events++;
		
		\Fwe::$app->stat('curl:act');
		$key = \Fwe::$app->stat('curl:req') - 1;
		$var = $this->_vars[$key % $this->maxThreads];

		$req->key = $this->_var->getKey();
		$req->resKey = $key;
		$var[$key] = $req;
		$this->_call[$key] = [$req, $call];
		
		$var->write();
	}
	
	public function read() {
		if(!$this->_var->read()) return;
		
		if(!--$this->_events) $this->_event->del();

		\Fwe::$app->events--;
		
		\Fwe::$app->stat('curl:act', -1);
		\Fwe::$app->stat('curl:res');

		$key = null;
		$res = $this->_var->shift(true, $key);

		list($req, $call) = $this->_call[$key];
		unset($this->_call[$key]);
		
		$call($res, $req);
	}
	
	public function cancel($key) {
		if(!isset($this->_call[$key])) return;
	
		if(!--$this->_events) $this->_event->del();
		
		unset($this->_call[$key]);
		
		\Fwe::$app->events--;
		\Fwe::$app->stat('curl:act', -1);
		\Fwe::$app->stat('curl:res');
		
		$this->_vars[$key % $this->maxThreads][$key] = false;
	}
}
