<?php
namespace fwe\db;

use fwe\traits\MethodProperty;

/**
 * @author abao
 * @property-read $time int
 * @property-read $affectedRows int
 * @property-read $insertId int
 * @property-read $error array
 */
class MySQLConnection extends AsyncConnection {
	use MethodProperty;
	
	/**
	 * @var \mysqli
	 */
	private $_mysqli;
	
	/**
	 * @var float
	 */
	private $_time;
	
	/**
	 * @var bool
	 */
	private $_isMaster;
	
	/**
	 * @see MySQLPool::push()
	 * @see MySQLPool::pop()
	 * 
	 * @var int
	 */
	public $iUsed;
	
	public function __construct(string $host, int $port, string $username, string $password, string $database, MySQLPool $pool, ?string $socket = null, bool $isMaster = false, string $charset = 'utf8') {
		$this->pool = $pool;
		$this->_mysqli = new \mysqli($host, $username, $password, $database, $port, $socket);
		$this->_mysqli->set_charset($charset);
		$this->_time = microtime(true);
		$this->_isMaster = $isMaster;
	}
	
	public function getTime() {
		return $this->_time;
	}
	
	public function isMaster() {
		return $this->_isMaster;
	}
	
	public function getAffectedRows() {
		return $this->_mysqli->affected_rows;
	}
	
	public function getInsertId() {
		return $this->_mysqli->insert_id;
	}
	
	public function getError() {
		return [$this->_mysqli->errno, $this->_mysqli->error];
	}
	
	public function ping() {
		return $this->_mysqli->ping();
	}
	
	/**
	 * @param string $sql
	 * @param int $resultMode
	 * @return \mysqli_result|boolean
	 */
	public function query(string $sql, int $resultMode = MYSQLI_STORE_RESULT) {
		$ret = $this->_mysqli->query($sql, $resultMode);
		$this->_time = microtime(true);
		return $ret;
	}
	
	/**
	 * @return \mysqli_result
	 */
	public function reapAsyncQuery() {
		$ret = $this->_mysqli->reap_async_query();
		$this->_time = microtime(true);
		return $ret;
	}
	
	public function bindParam(array &$param, &$types) {
		$types = '';
		foreach($param as &$val) {
			switch(gettype($val)) {
				case 'boolean':
					$types.='i';
					$val = $val?1:0;
					break;
				case 'integer':
					$types.='i';
					break;
				case 'double':
				case 'float':
					$types.='d';
					break;
				case 'string':
					$types.='s';
					break;
				case 'array':
					$types.='b';
					$val = json_encode($val);
					break;
				case 'object':
					$types.='b';
					$val = json_encode($val);
					break;
				default:
					$types.='s';
					$val = null;
					break;
			}
		}
		unset($val);
	}
	
	/**
	 * @param string $sql
	 * @return \mysqli_stmt
	 */
	public function prepare(string $sql) {
		$ret = $this->_mysqli->prepare($sql);
		$this->_time = microtime(true);
		return $ret;
	}
	
	/**
	 * @see MySQLQueryEvent::__construct()
	 * 
	 * @param string $sql
	 * @param array $options
	 * @return \fwe\db\MySQLConnection
	 */
	public function asyncQuery(string $sql, array $options = []) {
		$event = \Fwe::createObject(MySQLQueryEvent::class, [
			'db' => $this,
			'sql' => $sql,
		] + $options);
		$this->_events[] = $event;
		return $this;
	}
	
	/**
	 * @return \fwe\db\MySQLConnection
	 */
	public function asyncPrepare(string $sql, array $param, array $options = []) {
		$event = \Fwe::createObject(MySQLStmtEvent::class, [
			'db' => $this,
			'sql' => $sql,
			'param' => $param,
		] + $options);
		$this->_events[] = $event;
		return $this;
	}
	
	public function getFd() {
		return mysqli_export_fd($this->_mysqli);
	}
	
	public function __destruct() {
		$this->_mysqli->close();
	}
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
