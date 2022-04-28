<?php
namespace fwe\db;

class RedisEvent implements IEvent {

	protected $_db;
	protected $_name;
	protected $_params;
	protected $_command;
	
	protected $_key;
	protected $_success, $_error;
	protected $_data;

	protected $_time;
	
	public function __construct(RedisConnection $db, string $name, string $command, array $params, $key, ?callable $success = null, ?callable $error = null) {
		$this->_db = $db;
		$this->_name = $name;
		$this->_command = $command;
		$this->_params = $params;
		
		$this->_key = $key === null ? $db->eventKey++ : $key;
		$this->_success = $success;
		$this->_error = $error;
	}
	
	public function getSql() {
		return $this->_command;
	}
	
	public function getKey() {
		return $this->_key;
	}
	
	public function getData() {
		return $this->_data;
	}
	
	public function send() {
		$this->_time = microtime(true);
		$this->_db->sendCommandInternal($this->_command, $this->_params);
	}
	
	public function recv() {
		$this->_data = $this->_db->multiParseResponse($this->_params);

		$t = round(microtime(true) - $this->_time, 6);
		$cmd = $this->_db->formatCommandParams($this->_params);
		\Fwe::$app->info("Run time $t second, Command: {$cmd}", 'redis');
		
		if($this->_success) $this->_data = call_user_func($this->_success, $this->_data, $this->_db);
	}
	
	public function error(\Throwable $e) {
		$err = $e->getMessage();
		$t = round(microtime(true) - $this->_time, 6);
		$cmd = $this->_db->formatCommandParams($this->_params);
		\Fwe::$app->error("Run time $t second, Command: {$cmd}, ERROR: $err", 'redis');
		
		if($e instanceof SocketException) {
			$this->_db->close();
		}

		$this->_data = $e;
		if($this->_error) {
			$this->_data = call_user_func($this->_error, $this->_data, $this->_db);
		} else {
			throw $e;
		}
	}

}