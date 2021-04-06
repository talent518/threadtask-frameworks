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
class MySQLConnection {
	use MethodProperty;
	
	/**
	 * @var \mysqli
	 */
	private $_mysqli;
	
	/**
	 * @var int
	 */
	private $_time;
	
	/**
	 * @var bool
	 */
	private $_isMaster;
	
	/**
	 * @var MySQLPool
	 */
	public $pool;
	
	/**
	 * @see MySQLPool::push()
	 * @see MySQLPool::pop()
	 * 
	 * @var int
	 */
	public $iUsed;

	/**
	 * @var array
	 */
	private $_events = [], $_data = [], $_callbacks = [];
	
	/**
	 * @var MySQLEvent
	 */
	private $_current;
	
	/**
	 * @var \Event
	 */
	private $_event;
	
	/**
	 * @var int
	 */
	public $eventKey = 0;
	
	public function __construct(string $host, int $port, string $username, string $password, string $database, MySQLPool $pool, ?string $socket = null, bool $isMaster = false) {
		$this->pool = $pool;
		$this->_mysqli = new \mysqli($host, $username, $password, $database, $port, $socket);
		$this->_time = time();
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
	
	/**
	 * @param string $sql
	 * @param int $resultMode
	 * @return \mysqli_result|boolean
	 */
	public function query(string $sql, int $resultMode = MYSQLI_STORE_RESULT) {
		$ret = $this->_mysqli->query($sql, $resultMode);
		$this->_time = time();
		return $ret;
	}
	
	/**
	 * @return \mysqli_result
	 */
	public function reapAsyncQuery() {
		$ret = $this->_mysqli->reap_async_query();
		$this->_time = time();
		return $ret;
	}
	
	/**
	 * @param string $sql
	 * @return \mysqli_stmt
	 */
	public function prepare(string $sql) {
		$ret = $this->_mysqli->prepare($sql);
		$this->_time = time();
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
	public function asyncPrepare() {
		return $this;
	}
	
	protected function reset() {
		$this->_events = [];
		$this->_data = [];
		$this->_callbacks = [];
		$this->_current = null;
		if($this->_event) {
			$this->_event->del();
			$this->_event = null;
		}
	}
	
	protected function trigger(\Throwable $e = null) {
		if($e === null) {
			$data = $this->_data;
			$callback = $this->_callbacks[0];
			$this->reset();
			$ret = true;
			try {
				$ret = \Fwe::invoke($callback, $data + ['db'=>$this]) !== false;
			} catch(\Throwable $e) {
				echo $e;
				goto err;
			} finally {
				$this->reset();
				if($ret) {
					$this->pool->push($this);
				}
			}
		} else {
			err:
			$ret = true;
			try {
				$ret = \Fwe::invoke($this->_callbacks[1], ['db'=>$this, 'data'=>$this->_data, 'e'=>$e, 'event'=>$this->_current]) !== false;
			} catch(\Throwable $e) {
				echo $e;
			} finally {
				if($ret) {
					$this->reset();
					$this->pool->push($this);
				} else {
					if(MySQLEvent::FETCH_COLUMN_ALL) {
						$this->_data[$this->_current->getKey()] = $this->_current->getData();
					}
					$this->send();
				}
			}
		}
	}
	
	protected function send() {
		$this->_current = array_shift($this->_events);
		if($this->_current === null) {
			$this->trigger();
		} else {
			try {
				$this->_current->send();
			} catch(\Throwable $e) {
				echo $e;
				$this->trigger($e);
			}
		}
	}
	
	public function eventCallback() {
		if($this->_current === null) {
			$this->trigger(new Exception("没有要处理的事件"));
		} else {
			try {
				$this->_current->recv();
				$this->_data[$this->_current->getKey()] = $this->_current->getData();
				$this->send();
			} catch(\Throwable $e) {
				echo $e;
				$this->trigger($e);
			}
		}
	}
	
	public function goAsync(callable $success, callable $error) {
		if($this->_event === null) {
			if($this->_events) {
				$fd = mysqli_export_fd($this->_mysqli);
				if(!is_int($fd)) {
					$this->_events = [];
					return false;
				}

				$this->_event = new \Event(\Fwe::$base, $fd, \Event::READ | \Event::PERSIST, [$this, 'eventCallback']);
				$this->_event->add();
				$this->_callbacks = [$success, $error];
				$this->send();
				
				return true;
			} else {
				echo new Exception("异步事件队列为空");
				return false;
			}
		} else {
			echo new Exception("异步事件正在执行");
			return false;
		}
	}
	
	public function __destruct() {
		$this->_mysqli->close();
	}
}
