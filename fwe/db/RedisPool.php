<?php
namespace fwe\db;

class RedisPool implements IPool {

	public $id;

	public $host = '127.0.0.1';
	public $port = 6379;

	public $auth;
	
	public $unixSocket;
	public $database = 0;
	public $useSSL = false;
	public $socketClientFlags = STREAM_CLIENT_CONNECT;
	
	public $connectionString;
	public $connectionTimeout;
	public $dataTimeout;
	
	private $_pool = [], $_used = [];

	/**
	 * 压入一个Redis连接
	 * 
	 * @param $db RedisConnection
	 */
	public function push($db) {
		if(!is_int($db->iUsed)) return;
		
		$this->_pool[] = $db;
		unset($this->_used[$db->iUsed]);

		$db->iUsed = null;
	}

	/**
	 * 弹出一个MySQL连接
	 * 
	 * @return RedisConnection
	 */
	public function pop(bool $isAsync = false) {
		$db = array_pop($this->_pool);
		if($db === null) {
			$db = \Fwe::createObject(RedisConnection::class, [
				'host' => $this->host,
				'port' => $this->port,
				'auth' => $this->auth,
				'unixSocket' => $this->unixSocket,
				'database' => $this->database,
				'useSSL' => $this->useSSL,
				'socketClientFlags' => $this->socketClientFlags,
				'connectionString' => $this->connectionString,
				'connectionTimeout' => $this->connectionTimeout,
				'dataTimeout' => $this->dataTimeout,
				'pool' => $this,
			]);
		}
		$this->_used[] = $db;
		$db->iUsed = array_key_last($this->_used);
		if(!$db->ping()) {
			$this->remove($db);
			throw new Exception('Redis Ping Failure');
		}
		$db->pop($this);
		
		return $db;
	}

	/**
	 * @param $db RedisConnection
	 */
	public function remove($db) {
		if(!is_int($db->iUsed)) return;
		
		unset($this->_used[$db->iUsed]);
		
		$db->iUsed = null;
	}
	
	public function clean(float $time): string {
		/* @var $redis RedisConnection */
		
		$rets = [];

		$n = $t = 0;
		foreach($this->_pool as $i => $redis) {
			$t ++;
			if($redis->getTime() < $time) {
				$redis->reset();
				$redis->close();
				unset($this->_pool[$i]);
				$n ++;
			}
		}
		$rets[] = "p: $n/$t";
		
		$n = $t = 0;
		foreach($this->_used as $i => $redis) {
			$t ++;
			if($redis->getTime() < $time && !$redis->isUsing()) {
				$redis->reset();
				$redis->close();
				unset($this->_used[$i]);
				$n ++;
			}
		}
		$rets[] = "u: $n/$t";
		
		return implode(', ', $rets);
	}

}
