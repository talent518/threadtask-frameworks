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
}