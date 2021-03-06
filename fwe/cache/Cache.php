<?php
namespace fwe\cache;

use fwe\base\TsVar;

class Cache {
	/**
	 * @var TsVar
	 */
	protected $_var, $_name, $_notify;
	
	/**
	 * @var array
	 */
	protected $_keys = [], $_notifies = [];
	
	/**
	 * @var \Event
	 */
	protected $_event;
	
	protected $_prefix;
	
	/**
	 * 最大过期时间(以秒为单位)
	 * 
	 * @var integer
	 */
	public $expire = 0;
	
	public function __construct(string $prefix = '__cache') {
		$this->_prefix = $prefix;
		
		$this->_var = new TsVar("{$this->_prefix}:_var_");
		$this->_name = new TsVar("{$this->_prefix}:_name_");
		
		$name = THREAD_TASK_NAME;
		$this->_notify = new TsVar("{$this->_prefix}:{$name}__", 0, null, true);
		$this->_notify->bindReadEvent(function(int $len, string $buf) {
			for($i = 0; $i < $len; $i ++) {
				$key = $this->_notify->shift();
				$this->notify($key, $this->_var->get($key));
			}
		});
		$this->_notifies[$name] = $this->_notify;
		$this->_name->set($name, 0);
		
		\Fwe::$config->getOrSet("{$this->_prefix}:once-cache", function() {
			$this->_event = new \Event(\Fwe::$base, -1, \Event::TIMEOUT | \Event::PERSIST, function() {
				$this->_var->clean(time());
			});
			return $this->_event->addTimer(0.25); // 每1/4秒清理一次过期数据
		});
	}
	
	public function __destruct() {
		$this->_keys = $this->_notifies = null;
		$this->_var = $this->_notify = null;
		
		if($this->_event) {
			$this->_event->free();
			$this->_event = null;
		}
	}
	
	protected function notify(string $key, $value) {
		if(isset($this->_keys[$key])) {
			$oks = $this->_keys[$key];
			unset($this->_keys[$key]);
			
			\Fwe::$app->stat('cache:get', count($oks));
			
			foreach($oks as $ok) {
				\Fwe::$app->events --;
				
				try {
					$ok($value);
				} catch(\Throwable $e) {
					\Fwe::$app->error($e, 'cache');
				}
			}
		}
	}
	
	/**
	 * @param string $key
	 * @param callable $ok
	 * @param callable $set
	 * @param int $expire
	 * 
	 * @return Cache
	 */
	public function get(string $key, callable $ok, callable $set, int $expire = 0) {
		$call = function() use($key, $ok, $set, $expire) { // 当缓存中不存在时调用$set回调函数重新生成数据
			try {
				$set(function($value, ?int $expire2 = null) use($key, $expire) {
					if($expire2 !== null) {
						$expire = $expire2;
					}
					
					if($this->expire > 0 && $expire > $this->expire) {
						$expire = $this->expire;
					}
					
					$ret = $this->set($key, $value, $expire > 0 ? $expire + time() : 0);
					
					$names = $this->_name->keys();
					$i = array_search(THREAD_TASK_NAME, $names);
					unset($names[$i]);
					foreach($names as $name) {
						if(!isset($this->_notifies[$name])) {
							$this->_notifies[$name] = new TsVar("{$this->_prefix}:{$name}__", 0, null, true);
						}
						/* @var $var TsVar */
						$var = $this->_notifies[$name];
						$var->push($key);
						$var->write();
					}
					
					$this->notify($key, $value);
					
					return $ret;
				});
				return new Notify($key);
			} catch(\Throwable $e) {
				\Fwe::$app->error($e, 'cache');
				return $e->getMessage();
			}
		};
		
		if($this->expire > 0 && $expire > $this->expire) {
			$expire = $this->expire;
		}
		
		$val = $this->_var->getOrSet($key, $call, $expire > 0 ? $expire + time() : 0);
		
		if($val instanceof Notify) {
			$this->_keys[$key][] = $ok;
			\Fwe::$app->events ++;
		} else {
			\Fwe::$app->stat('cache:get');
			$ok($val);
		}
		
		return $this;
	}
	
	/**
	 * @param string $key
	 * @param mixed $value
	 * @param int $expire
	 * 
	 * @return Cache
	 */
	public function set(string $key, $value, int $expire = 0) {
		$this->_var->set($key, $value, $expire);
		\Fwe::$app->stat('cache:set');
		
		return $this;
	}
	
	/**
	 * @param string $key
	 * @param callable $ok
	 *
	 * @return Cache
	 */
	public function del(string $key, callable $ok) {
		$this->_var->remove($key);
		$ok('OK');
		return $this;
	}
	
	/**
	 * @return boolean|array
	 */
	public function keys() {
		return $this->_var->keys();
	}
	
	/**
	 * @return boolean|array
	 */
	public function expires() {
		return $this->_var->expires();
	}
}
