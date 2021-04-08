<?php
namespace fwe\db;

class RedisPool {

	public $id;

	public $host = 'localhost';

	public $port = 6379;

	public $auth;

	public function __construct() {
	}

	public function push(RedisConection $redis) {
	}

	public function pop(bool $isSlave = true) {
	}

	public function remove(RedisConection $redis) {
	}
}