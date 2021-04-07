<?php
namespace fwe\db;

class MySQLPool {
	
	public $id;

	public $host = 'localhost';

	public $port = 3306;

	public $username;

	public $password;

	public $database;
	
	public $socket;
	
	public $masters = [];
	public $slaves = [];

	/**
	 * @var array
	 */
	private $_mPool = [], $_mUsed = [], $_mIndex = 0;
	
	/**
	 * @var array
	 */
	private $_sPool = [], $_sUsed = [], $_sIndex = 0;
	
	public function __construct(string $username, string $password, string $database) {
		$this->username = $username;
		$this->password = $password;
		$this->database = $database;
	}

	/**
	 * 压入一个MySQL连接
	 * 
	 * @param MySQLConnection $db
	 */
	public function push(MySQLConnection $db) {
		if(!is_int($db->iUsed)) return;
		
		if($db->isMaster()) {
			$this->_mPool[] = $db;
			unset($this->_mUsed[$db->iUsed]);
		} else {
			$this->_sPool[] = $db;
			unset($this->_sUsed[$db->iUsed]);
		}
		$db->iUsed = null;
	}
	
	/**
	 * 弹出一个MySQL连接
	 * 
	 * @return MySQLConnection
	 */
	public function pop(bool $isSalve = true) {
		reconn:
		$isNew = false;
		/* @var $db MySQLConnection */
		if($isSalve && $this->slaves) {
			$db = array_pop($this->_sPool);
			if($db === null) {
				$db = \Fwe::createObject(MySQLConnection::class, $this->slaves[$this->_sIndex++] + [
					'host' => $this->host,
					'port' => $this->port,
					'username' => $this->username,
					'password' => $this->password,
					'database' => $this->database,
					'socket' => $this->socket,
					'pool' => $this,
				]);
				if($this->_sIndex === count($this->slaves)) {
					$this->_sIndex = 0;
				}
				$isNew = true;
			}
			$this->_sUsed[] = $db;
			$db->iUsed = array_key_last($this->_sUsed);
		} else {
			$db = array_pop($this->_mPool);
			if($db === null) {
				$params = [
					'host' => $this->host,
					'port' => $this->port,
					'username' => $this->username,
					'password' => $this->password,
					'database' => $this->database,
					'socket' => $this->socket,
					'pool' => $this,
					'isMaster' => true,
				];
				$db = \Fwe::createObject(MySQLConnection::class, $this->masters ? $this->masters[$this->_mIndex++] + $params : $params);
				if($this->_mIndex === count($this->masters)) {
					$this->_mIndex = 0;
				}
				$isNew = true;
			}
			$this->_mUsed[] = $db;
			$db->iUsed = array_key_last($this->_mUsed);
		}
		if(!$isNew && !$db->ping()) {
			$this->remove($db);
			goto reconn;
		}
		return $db;
	}
	
	public function remove(MySQLConnection $db) {
		if(!is_int($db->iUsed)) return;
		
		if($db->isMaster()) {
			unset($this->_mUsed[$db->iUsed]);
		} else {
			unset($this->_sUsed[$db->iUsed]);
		}
		$db->iUsed = null;
	}
}