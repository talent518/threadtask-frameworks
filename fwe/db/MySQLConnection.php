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
	 * 
	 * @var string $_host
	 * @var int $_port
	 * @var string $_username
	 * @var string $_password
	 * @var string $_database
	 * @var ?string $_socket
	 * @var string $_charset
	 */
	protected $_host, $_port, $_username, $_password, $_database, $_socket, $_charset;
	
	/**
	 * @var \mysqli
	 */
	protected $_mysqli;
	
	/**
	 * @var float
	 */
	protected $_time;
	
	/**
	 * @var bool
	 */
	protected $_isMaster;
	
	/**
	 * @see MySQLPool::push()
	 * @see MySQLPool::pop()
	 * 
	 * @var int
	 */
	public $iUsed;
	
	public function __construct(MySQLPool $pool, string $host, int $port, string $username, string $password, string $database, ?string $socket = null, string $charset = 'utf8', bool $isMaster = false) {
		$this->_pool = $pool;

		$this->_host = $host;
		$this->_port = $port;
		$this->_username = $username;
		$this->_password = $password;
		$this->_database = $database;
		$this->_socket = $socket;
		$this->_charset = $charset;

		$this->_isMaster = $isMaster;
		
		$this->open();
	}
	
	public function open() {
		if(!$this->_mysqli) {
			$this->_mysqli = new \mysqli($this->_host, $this->_username, $this->_password, $this->_database, $this->_port, $this->_socket);
			$this->_mysqli->set_charset($this->_charset);
			$this->_time = microtime(true);
		}
	}
	
	public function autoCommit(bool $mode) {
		return $this->_mysqli->autocommit($mode);
	}
	
	public function beginTransaction(int $flags = 0, ?string $name = null) {
		return $this->_mysqli->begin_transaction($flags, $name);
	}
	
	public function commit(int $flags = 0, ?string $name = null) {
		return $this->_mysqli->commit($flags, $name);
	}
	
	public function rollback() {
		return $this->_mysqli->rollback();
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
	
	public function ping(): bool {
		if($this->_mysqli) {
			try {
				return $this->_mysqli->ping();
			} catch(\Throwable $e) {
				try {
					$this->close();
					$this->open();
					return $this->_mysqli->ping();
				} catch(\Throwable $e) {
					\Fwe::$app->error($e, 'mysql-ping');
					$this->close();
					return false;
				}
			}
		} else {
			return false;
		}
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
		if($param) {
			$event = \Fwe::createObject(MySQLStmtEvent::class, [
				'db' => $this,
				'sql' => $sql,
				'param' => $param,
			] + $options);
		} else {
			$event = \Fwe::createObject(MySQLQueryEvent::class, [
				'db' => $this,
				'sql' => $sql,
			] + $options);
		}
		$this->_events[] = $event;
		return $this;
	}
	
	protected function getFd() {
		return $this->_mysqli ? mysqli_export_fd($this->_mysqli) : 0;
	}
	
	public function remove() {
		parent::remove();

		$this->close();
	}
	
	public function close() {
		if($this->_mysqli) {
			$this->_mysqli->close();
			$this->_mysqli = null;
		}
	}
	
	public function isClosed(): bool {
		return !$this->_mysqli;
	}
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
