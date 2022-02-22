<?php
namespace fwe\db;

class RedisEvent implements IEvent {

	protected $_db;
	protected $_name;
	protected $_params;
	protected $_command;
	
	protected $_key;
	protected $_callback;
	protected $_data;

	public function __construct(RedisConnection $db, string $name, string $command, array $params, $key, $callback) {
		$this->_db = $db;
		$this->_name = $name;
		$this->_command = $command;
		$this->_params = $params;
		
		$this->_key = $key === null ? $db->eventKey++ : $key;
		$this->_callback  = $callback;
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
		$this->_db->sendCommandInternal($this->_command, $this->_params);
	}
	
	public function recv() {
		$this->_data = $this->_db->multiParseResponse($this->_params);

		if($this->_callback) $this->_data = call_user_func($this->_callback, $this->_data, $this->_db);
	}

}