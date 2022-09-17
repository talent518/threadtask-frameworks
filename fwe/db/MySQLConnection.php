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
	
	const LEVEL_REPEATABLE_READ = 1;
	const LEVEL_READ_COMMITTED = 2;
	const LEVEL_READ_UNCOMMITTED = 3;
	const LEVEL_SERIALIZABLE = 4;
	
	const MODE_READ_WRITE = 1;
	const MODE_READ_ONLY = 2;

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
	 * @var bool
	 */
	protected $_isMaster, $_isConnected;
	
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
		$this->_isConnected = false;
		
		$this->_mysqli = new \mysqli();
	}
	
	/**
	 * @return string
	 */
	public function getHost() {
		return $this->_host;
	}
	
	/**
	 * @return number
	 */
	public function getPort() {
		return $this->_port;
	}
	
	/**
	 * @return string
	 */
	public function getUsername() {
		return $this->_username;
	}
	
	/**
	 * @return string
	 */
	public function getPassword() {
		return $this->_password;
	}
	
	/**
	 * @return string
	 */
	public function getDatabase() {
		return $this->_database;
	}
	
	/**
	 * @return \fwe\db\?string
	 */
	public function getSocket() {
		return $this->_socket;
	}
	
	/**
	 * @return string
	 */
	public function getCharset() {
		return $this->_charset;
	}
	
	public function isMaster() {
		return $this->_isMaster;
	}
	
	public function open() {
		if($this->_isConnected) {
			return;
		}
		$this->_isConnected = true;
		$this->_mysqli->connect($this->_host, $this->_username, $this->_password, $this->_database, $this->_port, $this->_socket);
		$this->_mysqli->set_charset($this->_charset);
		$this->_time = microtime(true);
	}
	
	public function autoCommit(bool $mode) {
		return $this->_mysqli->autocommit($mode);
	}
	
	public function beginTransaction(int $flags = 0) {
		return $this->_mysqli->begin_transaction($flags);
	}
	
	public function setTransaction(int $level = 0, int $mode = 0) {
		$suffix = null;
		switch($level) {
			case self::LEVEL_REPEATABLE_READ:
				$suffix .= ' ISOLATION LEVEL REPEATABLE READ';
				break;
			case self::LEVEL_READ_COMMITTED:
				$suffix .= ' ISOLATION LEVEL READ COMMITTED';
				break;
			case self::LEVEL_READ_UNCOMMITTED:
				$suffix .= ' ISOLATION LEVEL READ UNCOMMITTED';
				break;
			case self::LEVEL_SERIALIZABLE:
				$suffix .= ' ISOLATION LEVEL SERIALIZABLE';
				break;
			default:
				return;
		}
		
		switch($mode) {
			case self::MODE_READ_WRITE:
				$suffix .= ' READ WRITE';
				break;
			case self::MODE_READ_ONLY:
				$suffix .= ' READ ONLY';
				break;
			default:
				break;
		}
		
		return $this->query("SET TRANSACTION{$suffix}");
	}
	
	public function commit() {
		return $this->_mysqli->commit();
	}
	
	public function rollback() {
		return $this->_mysqli->rollback();
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
		if($this->_isConnected) {
			try {
				return $this->_mysqli->ping();
			} catch(\Throwable $e) {
				try {
					$this->close();
					$this->open();
					return true;
				} catch(\Throwable $e) {
					\Fwe::$app->error($e, 'mysql-ping');
					$this->close();
					return false;
				}
			}
		} else {
			try {
				$this->open();
				return true;
			} catch(\Throwable $e) {
				\Fwe::$app->error($e, 'mysql-ping');
				$this->close();
				return false;
			}
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
	 * @param string $sql
	 * @param array $param
	 * @param array $options
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
		if($this->_isConnected) {
			$this->_isConnected = false;
			$this->_mysqli->close();
		}
	}
	
	public function isClosed(): bool {
		return !$this->isConnected;
	}
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
